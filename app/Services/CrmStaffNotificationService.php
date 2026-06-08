<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalEvent;
use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\RepairJob;
use Illuminate\Support\Facades\Log;

final class CrmStaffNotificationService
{
    public function __construct(
        private readonly CrmApiService $crm,
        private readonly ApprovalEventService $approvalEvents,
    ) {}

    public function notifyHandoverFlaggedToMechanic(RepairJob $job, Mechanic $mechanic): void
    {
        $this->dispatch(
            mechanic: $mechanic,
            job: $job,
            trigger: 'handover_flagged',
            subject: 'A customer flagged an item at handover',
            body: "The customer flagged an item during handover on {$this->vehicleHeader($job)}. Please review the handover inspection.",
        );
    }

    public function notifyPaymentConfirmedToMechanic(RepairJob $job, Mechanic $mechanic): void
    {
        $this->dispatch(
            mechanic: $mechanic,
            job: $job,
            trigger: 'payment_confirmed',
            subject: 'Payment confirmed for repair job',
            body: "Payment has been confirmed for {$this->vehicleHeader($job)}. The job can be released for collection.",
        );
    }

    public function notifyTimeoutReminderToMechanic(RepairJob $job, Mechanic $mechanic): void
    {
        $this->dispatch(
            mechanic: $mechanic,
            job: $job,
            trigger: 'timeout_alert',
            subject: 'Job awaiting action exceeded 24h',
            body: "Repair job {$this->vehicleHeader($job)} has been in state {$job->state} for over 24 hours.",
        );
    }

    private function vehicleHeader(RepairJob $job): string
    {
        $job->loadMissing(['vehicle', 'garage']);
        $vehicle = $job->vehicle;

        return "{$vehicle->make} {$vehicle->model} ({$vehicle->registration})";
    }

    private function dispatch(
        Mechanic $mechanic,
        RepairJob $job,
        string $trigger,
        string $subject,
        string $body,
    ): void {
        $user = $mechanic->user;
        $crmUserId = $user === null ? '' : (string) ($user->crm_user_id ?? '');

        if ($crmUserId === '') {
            Log::warning('Staff notification skipped — mechanic has no crm_user_id mapping', [
                'mechanic_id' => $mechanic->id,
                'user_id' => $mechanic->user_id,
                'trigger' => $trigger,
                'job_id' => $job->id,
            ]);

            return;
        }

        $channels = $this->resolveChannels($mechanic, $job->garage);
        $crmEnabled = (bool) config('services.garage.staff_notifications_via_crm_enabled', false);

        foreach ($channels as $channel) {
            $this->crm->sendStaffNotification(
                crmUserId: $crmUserId,
                channel: $channel,
                subject: $subject,
                body: $body,
                meta: ['job_id' => $job->id, 'trigger' => $trigger, 'mechanic_id' => $mechanic->id],
            );

            $this->approvalEvents->recordBySystem($job, ApprovalEvent::EVENT_STAFF_NOTIFICATION_DISPATCHED, [
                'mechanic_id' => $mechanic->id,
                'channel' => $channel,
                'trigger' => $trigger,
                'crm_dispatched' => $crmEnabled,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveChannels(Mechanic $mechanic, Garage $garage): array
    {
        if (! $mechanic->canToggleChannels()) {
            return Garage::CHANNELS;
        }

        // When the mechanic has a preference row this is where it would be honored.
        // For now: in_app is the always-on mandatory minimum (Poka-Yoke); email/sms opt-in via toggle.
        return [Garage::CHANNEL_IN_APP, Garage::CHANNEL_EMAIL];
    }
}
