<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Services\CrmApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Locks the wire contract against the deployed CRM (`inteteam_crm`).
 *
 * These assertions exist because every one of them was wrong: the header the CRM
 * reads, the path of the by-email route, the required `company_id` parameter, and
 * the response key the id is read from. Nothing in the old test suite covered the
 * request side — only faked responses — so all four defects were invisible.
 */
final class CrmApiContractTest extends TestCase
{
    private CrmApiService $crm;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.crm.url', 'https://crm.test');
        Config::set('services.crm.internal_url', 'https://crm.test');
        Config::set('services.crm.token', 'internal-token');
        Config::set('services.crm.company_id', 'company-ulid');
        Config::set('services.crm.company_slug', 'my-garage');

        Cache::flush();

        $this->crm = app(CrmApiService::class);
    }

    /**
     * The CRM's `VerifyInternalToken` reads `$request->bearerToken()`. The old
     * client sent `X-Internal-Secret`, a header the CRM never inspects, so every
     * internal call was answered 401.
     */
    public function test_internal_calls_authenticate_with_a_bearer_token(): void
    {
        Http::fake(['https://crm.test/*' => Http::response(['found' => false], 200)]);

        $this->crm->findCustomerByEmail('jane@example.com');

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer internal-token'));
    }

    /**
     * Deploys that only ever set CRM_API_SECRET keep authenticating instead of
     * silently turning every CRM call into a 401 on release day.
     */
    public function test_legacy_secret_is_used_when_token_is_unset(): void
    {
        Config::set('services.crm.token', null);
        Config::set('services.crm.secret', 'legacy-secret');

        Http::fake(['https://crm.test/*' => Http::response(['found' => false], 200)]);

        $this->crm->findCustomerByEmail('jane@example.com');

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer legacy-secret'));
    }

    /**
     * Real route is `internal/customers/by-email`, and `InternalController::byEmail`
     * returns 400 without `company_id`. The old client called
     * `internal/customers?email=` — a path that does not exist.
     */
    public function test_customer_lookup_hits_the_by_email_route_with_company_id(): void
    {
        Http::fake(['https://crm.test/*' => Http::response(['found' => false], 200)]);

        $this->crm->findCustomerByEmail('Jane@Example.com ');

        Http::assertSent(function (Request $r) {
            return str_contains($r->url(), '/api/v1/internal/customers/by-email')
                && str_contains($r->url(), 'email=jane%40example.com')
                && str_contains($r->url(), 'company_id=company-ulid');
        });
    }

    public function test_customer_lookup_returns_null_when_crm_reports_not_found(): void
    {
        Http::fake([
            'https://crm.test/*' => Http::response(['found' => false, 'bookings' => []], 200),
        ]);

        $this->assertNull($this->crm->findCustomerByEmail('nobody@example.com'));
    }

    public function test_customer_lookup_returns_payload_when_crm_reports_found(): void
    {
        Http::fake([
            'https://crm.test/*' => Http::response([
                'found' => true,
                'email' => 'jane@example.com',
                'customer_name' => 'Jane D.',
                'bookings' => [],
            ], 200),
        ]);

        $result = $this->crm->findCustomerByEmail('jane@example.com');

        $this->assertSame('Jane D.', $result['customer_name'] ?? null);
    }

    public function test_customer_lookup_is_skipped_without_a_company_id(): void
    {
        Config::set('services.crm.company_id', null);

        Http::fake(['https://crm.test/*' => Http::response(['found' => true], 200)]);

        $this->assertNull($this->crm->findCustomerByEmail('jane@example.com'));

        Http::assertNothingSent();
    }

    /**
     * The SSO exchange is the only CRM route that yields a customer **id**, and
     * it takes the company **slug** as a path segment.
     */
    public function test_sso_exchange_posts_the_access_token_to_the_slug_route(): void
    {
        Http::fake([
            'https://crm.test/api/v1/auth/customer/my-garage/sso/exchange' => Http::response([
                'data' => [
                    'token' => 'crm-jwt',
                    'expires_in' => 3600,
                    'customer' => ['id' => 'cust-1', 'name' => 'Jane', 'email' => 'jane@example.com', 'phone' => '123'],
                ],
            ], 200),
        ]);

        $result = $this->crm->exchangeCustomerSsoToken('sso-access-token');

        $this->assertSame('cust-1', $result['id']);
        $this->assertSame('Jane', $result['name']);

        Http::assertSent(function (Request $r) {
            return $r->url() === 'https://crm.test/api/v1/auth/customer/my-garage/sso/exchange'
                && $r->method() === 'POST'
                && ($r->data()['sso_access_token'] ?? null) === 'sso-access-token';
        });
    }

    /** Public + throttled on the CRM side — sending the internal token would be wrong. */
    public function test_sso_exchange_returns_null_on_rejection(): void
    {
        Http::fake([
            'https://crm.test/*' => Http::response(['message' => 'SSO token validation failed.'], 401),
        ]);

        $this->assertNull($this->crm->exchangeCustomerSsoToken('bad-token'));
    }

    public function test_sso_exchange_is_skipped_without_a_company_slug(): void
    {
        Config::set('services.crm.company_slug', null);

        Http::fake(['https://crm.test/*' => Http::response([], 200)]);

        $this->assertNull($this->crm->exchangeCustomerSsoToken('tok'));

        Http::assertNothingSent();
    }

    /** Per-garage override beats the app-wide default so multi-garage installs stay tenant-correct. */
    public function test_per_garage_company_override_wins(): void
    {
        Http::fake(['https://crm.test/*' => Http::response(['data' => []], 200)]);

        $this->crm->listPaymentsForCustomer('cust-1', 'garage-own-company');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'company_id=garage-own-company'));
    }

    /** Container-to-container host is preferred over the browser-facing one. */
    public function test_internal_url_is_preferred_over_public_url(): void
    {
        Config::set('services.crm.internal_url', 'http://inteteam_nginx');

        Http::fake(['*' => Http::response(['found' => false], 200)]);

        $this->crm->findCustomerByEmail('jane@example.com');

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'http://inteteam_nginx/'));
    }

    public function test_customer_lookup_by_id_capability_is_off_by_default(): void
    {
        Config::set('services.crm.customer_lookup_by_id_enabled', null);

        $this->assertFalse(app(CrmApiService::class)->canLookUpCustomerById());
    }
}
