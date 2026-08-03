<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RepairJob;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Customer-side job-event notifications, wrapping `CrmApiService::sendNotification`.
 *
 * **Every method here is best-effort and never throws.** `CrmApiService` raises a
 * `RuntimeException` on any non-2xx, and none of the callers
 * (`EstimateLifecycleController::send`, `ScopeChangeController::store`,
 * `LineItemResponseController::store`, `CheckJobTimeouts`) caught it — so a CRM
 * hiccup turned a *succeeded* domain write into a 500 for the mechanic. Worse,
 * `POST /api/v1/internal/notifications` is not deployed on the CRM yet, which
 * means those four flows 500 on every call in any environment with `CRM_API_URL`
 * set: send an estimate, raise a scope change or reply to a customer question and
 * the state transition commits, the audit row is written, and then the response
 * blows up. The user retries, and the second attempt hits the "estimate already
 * sent" guard.
 *
 * Notification delivery is a side effect of the domain event, not part of it. A
 * failure is logged at `error` (so it is alertable) and swallowed. The
 * append-only `ApprovalEvent` written by the caller remains the source of truth
 * for what happened.
 */
final class CrmNotificationService
{
    public function __construct(
        private readonly CrmApiService $crm,
    ) {}

    public function notifyEstimateSent(RepairJob $job): void
    {
        $this->send(
            $job,
            'estimate_sent',
            'Your estimate is ready for review',
            fn (string $v) => "Your estimate for {$v} is ready. Please review and approve or decline each item via your portal link.",
        );
    }

    public function notifyCustomerQuery(RepairJob $job, string $question): void
    {
        $this->send(
            $job,
            'customer_query',
            'A question about your repair',
            fn (string $v) => "A question has been raised about your {$v}: {$question}",
        );
    }

    public function notifyTimeoutReminder(RepairJob $job): void
    {
        $this->send(
            $job,
            'timeout',
            'Action required: your vehicle estimate',
            fn (string $v) => "A response is still needed for your {$v}. Please review your portal link at your earliest convenience.",
        );
    }

    public function notifyHandoverReady(RepairJob $job): void
    {
        $this->send(
            $job,
            'handover_ready',
            'Your vehicle is ready for collection',
            fn (string $v) => "Your {$v} is ready. Please collect it at your earliest convenience.",
        );
    }

    public function notifyScopeChange(RepairJob $job): void
    {
        $this->send(
            $job,
            'scope_change',
            'Additional work needed on your vehicle',
            fn (string $v) => "While working on your {$v} we found additional work that needs your approval. Please review the new items in your portal.",
        );
    }

    public function notifyMechanicResponse(RepairJob $job, string $message): void
    {
        $this->send(
            $job,
            'mechanic_response',
            'A response from your mechanic',
            fn (string $v) => "Your mechanic has replied about your {$v}: {$message}",
        );
    }

    /**
     * @param  callable(string): string  $body  Receives the "Make Model (REG)" header
     */
    private function send(RepairJob $job, string $trigger, string $subject, callable $body): void
    {
        $job->loadMissing(['vehicle', 'notificationPreference', 'garage']);
        $vehicle = $job->vehicle;
        $header = "{$vehicle->make} {$vehicle->model} ({$vehicle->registration})";

        try {
            $this->crm->sendNotification(
                crmCustomerId: $vehicle->crm_customer_id,
                channel: $this->resolveChannel($job),
                subject: $subject,
                body: $body($header),
                meta: ['job_id' => $job->id, 'trigger' => $trigger],
                companyId: $job->garage->crm_company_id,
            );
        } catch (Throwable $e) {
            Log::error('CRM customer notification failed — domain write kept', [
                'job_id' => $job->id,
                'trigger' => $trigger,
                'crm_customer_id' => $vehicle->crm_customer_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveChannel(RepairJob $job): string
    {
        $pref = $job->notificationPreference;

        // @phpstan-ignore-next-line nullsafe.neverNull — HasOne can return null when no record exists
        return $pref?->channel ?? $job->garage->default_notification_channel;
    }
}
