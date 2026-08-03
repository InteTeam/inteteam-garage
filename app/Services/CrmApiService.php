<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Bottom-layer HTTP client for inteteam_crm.
 *
 * Auth is `Authorization: Bearer <CRM_INTERNAL_TOKEN>` — the CRM's
 * `VerifyInternalToken` middleware reads `$request->bearerToken()` and
 * `hash_equals` it against its own `services.internal.token`. The previous
 * `X-Internal-Secret` header was never read by the CRM, so every internal call
 * was rejected with 401.
 *
 * Endpoint status against the deployed CRM (`inteteam_crm/routes/api.php`):
 *
 *  | Method                     | CRM route                                          | Live |
 *  |----------------------------|----------------------------------------------------|------|
 *  | findCustomerByEmail        | GET  /api/v1/internal/customers/by-email            | yes  |
 *  | exchangeCustomerSsoToken   | POST /api/v1/auth/customer/{company}/sso/exchange   | yes  |
 *  | getCustomer                | GET  /api/v1/internal/customers/{id}                | no   |
 *  | sendNotification           | POST /api/v1/internal/notifications                 | no   |
 *  | sendStaffNotification      | POST /api/v1/internal/notifications                 | no   |
 *  | createPaymentRequest       | POST /api/v1/internal/payments                      | no   |
 *  | listPaymentsForCustomer    | GET  /api/v1/internal/payments                      | no   |
 *
 * The "no" rows are the contract this app needs the CRM team to ship; see
 * `docs/features/crm-integration/contract.md`. They are all written so that an
 * absent endpoint degrades (logged, empty/false result, or a caught exception at
 * the domain-service layer) instead of taking a user-facing request down with it.
 */
final class CrmApiService
{
    /** @return array<string, mixed> */
    public function getCustomer(string $crmCustomerId): array
    {
        $response = $this->http()
            ->get("/api/v1/internal/customers/{$crmCustomerId}");

        $this->assertSuccess($response);

        return $response->json('data', []);
    }

    /**
     * True when `GET /api/v1/internal/customers/{id}` may be trusted to
     * distinguish "no such customer" from "no such route". Off by default.
     */
    public function canLookUpCustomerById(): bool
    {
        return (bool) config('services.crm.customer_lookup_by_id_enabled', false);
    }

    /**
     * Locate a CRM customer by email within a CRM company.
     *
     * Real CRM shape (`InternalController::byEmail`) is
     * `{found: bool, email, customer_name, crm_url, bookings: [...]}` — note it
     * carries **no customer id**, which is why the login path binds identity via
     * `exchangeCustomerSsoToken()` instead. This method answers "does the CRM
     * know this email", nothing more. `company_id` is required by the endpoint.
     *
     * Cached for 60s so a multi-tab / retried login burst makes one CRM call.
     * Returns null on 404, a non-2xx, or any transport error; the log line is how
     * "not a CRM customer yet" is told apart from a CRM outage.
     *
     * @return array<string, mixed>|null
     */
    public function findCustomerByEmail(string $email, ?string $companyId = null): ?array
    {
        $normalised = strtolower(trim($email));
        $companyId = $companyId ?? $this->defaultCompanyId();

        if ($normalised === '' || $companyId === null) {
            if ($companyId === null) {
                Log::warning('CRM customer lookup by email skipped — no CRM company id configured', [
                    'email' => $normalised,
                ]);
            }

            return null;
        }

        return Cache::remember(
            "crm_customer_by_email:{$companyId}:{$normalised}",
            now()->addSeconds(60),
            function () use ($normalised, $companyId): ?array {
                try {
                    $response = $this->http()->get('/api/v1/internal/customers/by-email', [
                        'email' => $normalised,
                        'company_id' => $companyId,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('CRM customer lookup by email failed', [
                        'email' => $normalised,
                        'error' => $e->getMessage(),
                    ]);

                    return null;
                }

                if ($response->status() === 404) {
                    return null;
                }

                if (! $response->successful()) {
                    Log::warning('CRM customer lookup by email returned non-2xx', [
                        'email' => $normalised,
                        'status' => $response->status(),
                    ]);

                    return null;
                }

                $body = $response->json();

                if (! is_array($body) || ($body['found'] ?? false) !== true) {
                    return null;
                }

                return $body;
            },
        );
    }

    /**
     * Exchange an Inte.Team SSO access token for the CRM's own view of the
     * customer. This is the same endpoint store_front uses (playbook →
     * architecture/sso-integration.md, "Next.js apps (Token Exchange pattern)")
     * and it is the only CRM route that returns a customer **id** — it finds or
     * creates the `Customer` in the given company and back-links their existing
     * bookings and orders by email.
     *
     * Public + throttled (10/min) on the CRM side, so no internal token is sent.
     *
     * @return array{id: string, name: ?string, email: ?string, phone: ?string}|null
     */
    public function exchangeCustomerSsoToken(string $ssoAccessToken, ?string $companySlug = null): ?array
    {
        $slug = $companySlug ?? $this->defaultCompanySlug();

        if ($slug === null) {
            Log::warning('CRM customer SSO exchange skipped — CRM_COMPANY_SLUG not configured');

            return null;
        }

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->timeout(15)
                ->acceptJson()
                ->post("/api/v1/auth/customer/{$slug}/sso/exchange", [
                    'sso_access_token' => $ssoAccessToken,
                ]);
        } catch (\Throwable $e) {
            Log::warning('CRM customer SSO exchange threw', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('CRM customer SSO exchange returned non-2xx', [
                'status' => $response->status(),
                'company_slug' => $slug,
            ]);

            return null;
        }

        $customer = $response->json('data.customer');

        if (! is_array($customer) || ! is_string($customer['id'] ?? null) || $customer['id'] === '') {
            Log::warning('CRM customer SSO exchange response missing data.customer.id');

            return null;
        }

        return [
            'id' => $customer['id'],
            'name' => is_string($customer['name'] ?? null) ? $customer['name'] : null,
            'email' => is_string($customer['email'] ?? null) ? $customer['email'] : null,
            'phone' => is_string($customer['phone'] ?? null) ? $customer['phone'] : null,
        ];
    }

    /**
     * Deliberately NOT gated on `canLookUpCustomerById()`: this path already
     * degrades correctly (any failure → null → caller falls back to the garage
     * locale), and gating it would silently keep translation locale resolution
     * switched off on the day the CRM ships the endpoint. The flag exists only for
     * the vehicle-form rule, where a 404 has to be interpreted rather than ignored.
     */
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

    /**
     * @param  array<string, mixed>  $meta
     * @param  string|null  $companyId  Per-garage CRM company override (`Garage.crm_company_id`);
     *                                  falls back to the app-wide `CRM_COMPANY_ID`.
     */
    public function sendNotification(
        string $crmCustomerId,
        string $channel,
        string $subject,
        string $body,
        array $meta = [],
        ?string $companyId = null,
    ): void {
        $response = $this->http()->post('/api/v1/internal/notifications', [
            'recipient_type' => 'customer',
            'recipient_id' => $crmCustomerId,
            'customer_id' => $crmCustomerId,
            'company_id' => $this->resolveCompanyId($companyId),
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
     * Feature-flagged via `services.garage.staff_notifications_via_crm_enabled`
     * until the CRM ships `recipient_type=staff`; when off this logs and returns
     * so the caller's audit row is the only record.
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
        ?string $companyId = null,
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
            'company_id' => $this->resolveCompanyId($companyId),
            'channel' => $channel,
            'subject' => $subject,
            'body' => $body,
            'meta' => $meta,
        ]);

        $this->assertSuccess($response);
    }

    /**
     * @param  list<array<string, mixed>>  $lineItems
     */
    public function createPaymentRequest(
        string $jobId,
        array $lineItems,
        float $total,
        string $crmCustomerId,
        ?string $companyId = null,
    ): string {
        $response = $this->http()->post('/api/v1/internal/payments', [
            'job_id' => $jobId,
            'line_items' => $lineItems,
            'total' => $total,
            'customer_id' => $crmCustomerId,
            'company_id' => $this->resolveCompanyId($companyId),
        ]);

        $this->assertSuccess($response);

        return (string) $response->json('reference');
    }

    /**
     * Payment history for a CRM customer (used by /account/transactions).
     * Returns empty list on lookup failure — the portal must remain reachable
     * even when CRM is partly degraded, so we don't propagate the exception.
     *
     * @return list<array<string, mixed>>
     */
    public function listPaymentsForCustomer(string $crmCustomerId, ?string $companyId = null): array
    {
        try {
            $response = $this->http()->get('/api/v1/internal/payments', [
                'customer_id' => $crmCustomerId,
                'company_id' => $this->resolveCompanyId($companyId),
            ]);
        } catch (\Throwable $e) {
            Log::warning('CRM payment history lookup failed', [
                'crm_customer_id' => $crmCustomerId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('CRM payment history returned non-2xx', [
                'crm_customer_id' => $crmCustomerId,
                'status' => $response->status(),
            ]);

            return [];
        }

        $data = $response->json('data');

        return is_array($data) ? array_values($data) : [];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout(15)
            // Matches the playbook's CRM-client pattern (cms/README.md). The CRM
            // sits behind NPM; a single dropped connection should not surface as a
            // domain failure.
            ->retry(2, 500, throw: false)
            ->acceptJson()
            ->withToken($this->token());
    }

    /**
     * Container-to-container host when configured, public URL otherwise.
     */
    private function baseUrl(): string
    {
        $internal = (string) config('services.crm.internal_url', '');

        return rtrim($internal !== '' ? $internal : (string) config('services.crm.url'), '/');
    }

    private function token(): string
    {
        $token = (string) config('services.crm.token', '');

        // Fallback keeps deploys that only ever set CRM_API_SECRET working, rather
        // than turning every CRM call into a silent 401 at release time.
        return $token !== '' ? $token : (string) config('services.crm.secret', '');
    }

    /**
     * Per-garage override wins over the app-wide default, so a multi-garage
     * install can point each garage at its own CRM company.
     */
    private function resolveCompanyId(?string $override): ?string
    {
        if (is_string($override) && $override !== '') {
            return $override;
        }

        return $this->defaultCompanyId();
    }

    private function defaultCompanyId(): ?string
    {
        $id = config('services.crm.company_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function defaultCompanySlug(): ?string
    {
        $slug = config('services.crm.company_slug');

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    private function assertSuccess(Response $response): void
    {
        if (! $response->successful()) {
            throw new RuntimeException(
                "CRM API error [{$response->status()}]: {$response->body()}"
            );
        }
    }
}
