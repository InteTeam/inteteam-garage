<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class CrmApiService
{
    private string $baseUrl;

    private string $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.crm.url'), '/');
        $this->secret = (string) config('services.crm.secret');
    }

    public function getCustomer(string $crmCustomerId): array
    {
        $response = $this->http()
            ->get("/api/v1/internal/customers/{$crmCustomerId}");

        $this->assertSuccess($response);

        return $response->json('data', []);
    }

    public function getCustomerLocale(string $crmCustomerId): ?string
    {
        try {
            $customer = Cache::remember(
                "crm_customer:{$crmCustomerId}",
                now()->addHour(),
                fn () => $this->getCustomer($crmCustomerId),
            );
        } catch (\Throwable $e) {
            Log::warning('CRM customer lookup failed during locale resolution', [
                'crm_customer_id' => $crmCustomerId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $locale = $customer['locale'] ?? null;

        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    public function sendNotification(
        string $crmCustomerId,
        string $channel,
        string $subject,
        string $body,
        array $meta = []
    ): void {
        $response = $this->http()->post('/api/v1/internal/notifications', [
            'recipient_type' => 'customer',
            'recipient_id' => $crmCustomerId,
            'customer_id' => $crmCustomerId,
            'channel' => $channel,
            'subject' => $subject,
            'body' => $body,
            'meta' => $meta,
        ]);

        $this->assertSuccess($response);
    }

    /**
     * Send a staff-recipient notification via the polymorphic CRM endpoint.
     *
     * Phase 5 contract: CRM team is shipping `recipient_type=staff` support; until then this
     * call short-circuits and the audit row is the only record. Feature-flag controlled via
     * `services.garage.staff_notifications_via_crm_enabled`.
     *
     * @param  string  $channel  One of `Garage::CHANNELS`
     * @param  array<string, mixed>  $meta  Arbitrary metadata persisted with the notification record
     */
    public function sendStaffNotification(
        string $crmUserId,
        string $channel,
        string $subject,
        string $body,
        array $meta = [],
    ): void {
        if (! (bool) config('services.garage.staff_notifications_via_crm_enabled', false)) {
            Log::info('Staff notification skipped — feature flag off; CRM endpoint not yet wired', [
                'recipient_type' => 'staff',
                'recipient_id' => $crmUserId,
                'channel' => $channel,
                'subject' => $subject,
                'meta' => $meta,
            ]);

            return;
        }

        $response = $this->http()->post('/api/v1/internal/notifications', [
            'recipient_type' => 'staff',
            'recipient_id' => $crmUserId,
            'channel' => $channel,
            'subject' => $subject,
            'body' => $body,
            'meta' => $meta,
        ]);

        $this->assertSuccess($response);
    }

    public function createPaymentRequest(string $jobId, array $lineItems, float $total, string $crmCustomerId): string
    {
        $response = $this->http()->post('/api/v1/internal/payments', [
            'job_id' => $jobId,
            'line_items' => $lineItems,
            'total' => $total,
            'customer_id' => $crmCustomerId,
        ]);

        $this->assertSuccess($response);

        return (string) $response->json('reference');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout(15)
            ->acceptJson()
            ->withHeader('X-Internal-Secret', $this->secret);
    }

    private function assertSuccess(Response $response): void
    {
        if (! $response->successful()) {
            throw new \RuntimeException(
                "CRM API error [{$response->status()}]: {$response->body()}"
            );
        }
    }
}
