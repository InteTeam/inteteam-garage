<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalEvent;
use App\Models\HandoverInspection;
use App\Models\HandoverItem;
use App\Models\Mechanic;
use App\Models\RepairJob;
use Illuminate\Support\Facades\DB;

final class HandoverInspectionService
{
    public function __construct(
        private readonly ApprovalEventService $approvalEventService,
        private readonly CrmStaffNotificationService $staffNotifications,
    ) {}

    /**
     * Atomically persists a customer-submitted handover inspection (inspection +
     * one row per line item) and emits the audit event. If any item insert fails
     * mid-loop, the inspection is rolled back so half-handovers never persist.
     *
     * After the commit, mechanics are notified iff any item was rejected or flagged.
     *
     * @param  list<array{line_item_id: string, accepted: bool, notes?: string|null}>  $items
     */
    public function submitFromPortal(RepairJob $job, array $items, string $token): HandoverInspection
    {
        $inspection = DB::transaction(function () use ($job, $items, $token): HandoverInspection {
            $inspection = HandoverInspection::withoutGlobalScopes()->create([
                'garage_id' => $job->garage_id,
                'job_id' => $job->id,
                'submitted_by_token' => $token,
                'submitted_at' => now(),
            ]);

            foreach ($items as $item) {
                HandoverItem::withoutGlobalScopes()->create([
                    'garage_id' => $job->garage_id,
                    'handover_inspection_id' => $inspection->id,
                    'line_item_id' => $item['line_item_id'],
                    'accepted' => $item['accepted'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $this->approvalEventService->record(
                job: $job,
                eventType: ApprovalEvent::EVENT_HANDOVER_SUBMITTED,
                actorType: ApprovalEvent::ACTOR_CUSTOMER,
                payload: ['items_count' => count($items)],
            );

            return $inspection;
        });

        $flagged = collect($items)->contains(
            fn (array $item): bool => $item['accepted'] === false || ! empty($item['notes'] ?? null)
        );

        if ($flagged) {
            $job->load('mechanics.user');
            $job->mechanics->each(function (Mechanic $mechanic) use ($job): void {
                $this->staffNotifications->notifyHandoverFlaggedToMechanic($job, $mechanic);
            });
        }

        return $inspection;
    }
}
