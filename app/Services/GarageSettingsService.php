<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalEvent;
use App\Models\Garage;
use App\Models\RepairJob;
use Illuminate\Support\Facades\DB;

final class GarageSettingsService
{
    public function __construct(
        private readonly ApprovalEventService $approvalEventService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Garage $garage, array $data, string $actorId): Garage
    {
        $previousToggle = (bool) $garage->online_payment_enabled;

        DB::transaction(function () use ($garage, $data, $previousToggle, $actorId): void {
            $garage->update($data);

            $newToggle = (bool) $garage->online_payment_enabled;

            if ($newToggle === $previousToggle) {
                return;
            }

            $activeJobs = RepairJob::withoutGlobalScopes()
                ->where('garage_id', $garage->id)
                ->where('state', '!=', RepairJob::STATE_COLLECTED)
                ->get();

            foreach ($activeJobs as $job) {
                $this->approvalEventService->record(
                    job: $job,
                    eventType: ApprovalEvent::EVENT_PREFERENCE_CHANGED,
                    actorType: ApprovalEvent::ACTOR_MECHANIC,
                    actorId: $actorId,
                    payload: [
                        'setting' => 'online_payment_enabled',
                        'from' => $previousToggle,
                        'to' => $newToggle,
                    ],
                );
            }
        });

        return $garage->fresh();
    }
}
