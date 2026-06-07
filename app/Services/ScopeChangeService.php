<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalEvent;
use App\Models\Estimate;
use App\Models\LineItem;
use App\Models\RepairJob;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ScopeChangeService
{
    public function __construct(
        private readonly JobStateMachine $stateMachine,
        private readonly ApprovalEventService $approvalEventService,
    ) {}

    /**
     * @param  array<int, array{description: string, price: float|string}>  $lineItems
     */
    public function create(RepairJob $job, array $lineItems, string $actorId): Estimate
    {
        if ($job->state !== RepairJob::STATE_APPROVED) {
            throw new RuntimeException(
                'Scope change can only be raised from the approved state.'
            );
        }

        if ($lineItems === []) {
            throw new RuntimeException(
                'Scope change requires at least one new line item.'
            );
        }

        return DB::transaction(function () use ($job, $lineItems, $actorId): Estimate {
            $nextRevision = ((int) $job->estimates()->max('revision_number')) + 1;

            /** @var Estimate $estimate */
            $estimate = Estimate::withoutGlobalScopes()->create([
                'garage_id' => $job->garage_id,
                'job_id' => $job->id,
                'revision_number' => $nextRevision,
                'sent_at' => now(),
            ]);

            foreach ($lineItems as $item) {
                LineItem::withoutGlobalScopes()->create([
                    'garage_id' => $job->garage_id,
                    'estimate_id' => $estimate->id,
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'status' => LineItem::STATUS_PENDING,
                ]);
            }

            $this->stateMachine->transition($job, RepairJob::STATE_SCOPE_CHANGE, $actorId);

            $this->approvalEventService->record(
                job: $job,
                eventType: ApprovalEvent::EVENT_SCOPE_CHANGE,
                actorType: ApprovalEvent::ACTOR_MECHANIC,
                actorId: $actorId,
                payload: [
                    'estimate_id' => $estimate->id,
                    'revision_number' => $nextRevision,
                    'line_item_count' => count($lineItems),
                ],
            );

            return $estimate;
        });
    }
}
