<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RepairJob;

final class CrmNotificationService
{
    public function __construct(
        private readonly CrmApiService $crm,
    ) {}

    public function notifyEstimateSent(RepairJob $job): void
    {
        $job->loadMissing(['vehicle', 'notificationPreference', 'garage']);
        $vehicle = $job->vehicle;

        $this->crm->sendNotification(
            crmCustomerId: $vehicle->crm_customer_id,
            channel: $this->resolveChannel($job),
            subject: 'Your estimate is ready for review',
            body: "Your estimate for {$vehicle->make} {$vehicle->model} ({$vehicle->registration}) is ready. Please review and approve or decline each item via your portal link.",
            meta: ['job_id' => $job->id, 'trigger' => 'estimate_sent'],
        );
    }

    public function notifyCustomerQuery(RepairJob $job, string $question): void
    {
        $job->loadMissing(['vehicle', 'notificationPreference', 'garage']);
        $vehicle = $job->vehicle;

        $this->crm->sendNotification(
            crmCustomerId: $vehicle->crm_customer_id,
            channel: $this->resolveChannel($job),
            subject: 'A question about your repair',
            body: "A question has been raised about your {$vehicle->make} {$vehicle->model} ({$vehicle->registration}): {$question}",
            meta: ['job_id' => $job->id, 'trigger' => 'customer_query'],
        );
    }

    public function notifyTimeoutReminder(RepairJob $job): void
    {
        $job->loadMissing(['vehicle', 'notificationPreference', 'garage']);
        $vehicle = $job->vehicle;

        $this->crm->sendNotification(
            crmCustomerId: $vehicle->crm_customer_id,
            channel: $this->resolveChannel($job),
            subject: 'Action required: your vehicle estimate',
            body: "A response is still needed for your {$vehicle->make} {$vehicle->model} ({$vehicle->registration}). Please review your portal link at your earliest convenience.",
            meta: ['job_id' => $job->id, 'trigger' => 'timeout'],
        );
    }

    public function notifyHandoverReady(RepairJob $job): void
    {
        $job->loadMissing(['vehicle', 'notificationPreference', 'garage']);
        $vehicle = $job->vehicle;

        $this->crm->sendNotification(
            crmCustomerId: $vehicle->crm_customer_id,
            channel: $this->resolveChannel($job),
            subject: 'Your vehicle is ready for collection',
            body: "Your {$vehicle->make} {$vehicle->model} ({$vehicle->registration}) is ready. Please collect it at your earliest convenience.",
            meta: ['job_id' => $job->id, 'trigger' => 'handover_ready'],
        );
    }

    public function notifyScopeChange(RepairJob $job): void
    {
        $job->loadMissing(['vehicle', 'notificationPreference', 'garage']);
        $vehicle = $job->vehicle;

        $this->crm->sendNotification(
            crmCustomerId: $vehicle->crm_customer_id,
            channel: $this->resolveChannel($job),
            subject: 'Additional work needed on your vehicle',
            body: "While working on your {$vehicle->make} {$vehicle->model} ({$vehicle->registration}) we found additional work that needs your approval. Please review the new items in your portal.",
            meta: ['job_id' => $job->id, 'trigger' => 'scope_change'],
        );
    }

    public function notifyMechanicResponse(RepairJob $job, string $message): void
    {
        $job->loadMissing(['vehicle', 'notificationPreference', 'garage']);
        $vehicle = $job->vehicle;

        $this->crm->sendNotification(
            crmCustomerId: $vehicle->crm_customer_id,
            channel: $this->resolveChannel($job),
            subject: 'A response from your mechanic',
            body: "Your mechanic has replied about your {$vehicle->make} {$vehicle->model} ({$vehicle->registration}): {$message}",
            meta: ['job_id' => $job->id, 'trigger' => 'mechanic_response'],
        );
    }

    private function resolveChannel(RepairJob $job): string
    {
        $pref = $job->notificationPreference;

        // @phpstan-ignore-next-line nullsafe.neverNull — HasOne can return null when no record exists
        return $pref?->channel ?? $job->garage->default_notification_channel;
    }
}
