<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

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

    public function sendNotification(
        string $crmCustomerId,
        string $channel,
        string $subject,
        string $body,
        array $meta = []
    ): void {
        $response = $this->http()->post('/api/v1/internal/notifications', [
            'customer_id' => $crmCustomerId,
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
