<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalEvent;
use App\Models\Garage;
use App\Models\RepairJob;
use Illuminate\Support\Facades\DB;

final class GarageSettingsService
{
    private const AUDITED_SETTINGS = [
        'online_payment_enabled' => ApprovalEvent::EVENT_PREFERENCE_CHANGED,
        'staff_channel_toggle_default' => ApprovalEvent::EVENT_STAFF_TOGGLE_LOCK_CHANGED,
        'timeout_reminder_policy' => ApprovalEvent::EVENT_TIMEOUT_POLICY_CHANGED,
    ];

    public function __construct(
        private readonly ApprovalEventService $approvalEventService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Garage $garage, array $data, string $actorId): Garage
    {
        $before = $this->snapshot($garage);

        DB::transaction(function () use ($garage, $data, $before, $actorId): void {
            $garage->update($data);
            $garage->refresh();

            $after = $this->snapshot($garage);
            $changes = $this->diff($before, $after);

            if (empty($changes)) {
                return;
            }

            $activeJobs = RepairJob::withoutGlobalScopes()
                ->where('garage_id', $garage->id)
                ->where('state', '!=', RepairJob::STATE_COLLECTED)
                ->get();

            foreach ($changes as $setting => $diff) {
                $eventType = self::AUDITED_SETTINGS[$setting];

                foreach ($activeJobs as $job) {
                    $this->approvalEventService->record(
                        job: $job,
                        eventType: $eventType,
                        actorType: ApprovalEvent::ACTOR_MECHANIC,
                        actorId: $actorId,
                        payload: [
                            'setting' => $setting,
                            'from' => $diff['from'],
                            'to' => $diff['to'],
                        ],
                    );
                }
            }
        });

        return $garage->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Garage $garage): array
    {
        return [
            'online_payment_enabled' => (bool) $garage->online_payment_enabled,
            'staff_channel_toggle_default' => (bool) $garage->staff_channel_toggle_default,
            'timeout_reminder_policy' => (string) $garage->timeout_reminder_policy,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($before as $key => $value) {
            if ($before[$key] !== $after[$key]) {
                $changes[$key] = ['from' => $before[$key], 'to' => $after[$key]];
            }
        }

        return $changes;
    }
}
