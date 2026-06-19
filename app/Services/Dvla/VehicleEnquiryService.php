<?php

declare(strict_types=1);

namespace App\Services\Dvla;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class VehicleEnquiryService
{
    private string $url;

    private ?string $apiKey;

    private int $timeout;

    public function __construct()
    {
        $this->url = (string) config('services.dvla.ves_url');
        $apiKey = config('services.dvla.ves_api_key');
        $this->apiKey = is_string($apiKey) && $apiKey !== '' ? $apiKey : null;
        $this->timeout = (int) config('services.dvla.ves_timeout', 10);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->url !== '';
    }

    /**
     * Look up a UK vehicle by registration. Returns parsed MOT/Tax dates plus enrichment fields.
     * DVLA strips whitespace and is case-insensitive; we normalise to uppercase no-space for cache parity.
     */
    public function fetch(string $registration): VehicleEnquiryResult
    {
        if (! $this->isConfigured()) {
            throw DvlaException::notConfigured();
        }

        $normalised = strtoupper(preg_replace('/\s+/', '', $registration) ?? '');

        $response = $this->http()->post('', [
            'registrationNumber' => $normalised,
        ]);

        if ($response->status() === 404) {
            throw DvlaException::registrationNotFound($normalised);
        }

        if ($response->status() === 400) {
            throw DvlaException::badRequest($normalised);
        }

        if (! $response->successful()) {
            throw DvlaException::unavailable($response->status(), $response->body());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw DvlaException::unavailable($response->status(), 'malformed JSON');
        }

        return VehicleEnquiryResult::fromApi($payload);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->url)
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withHeader('x-api-key', (string) $this->apiKey);
    }
}
