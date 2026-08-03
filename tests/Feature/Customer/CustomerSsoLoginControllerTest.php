<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CustomerSsoLoginControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Must match SsoService::STATE_SESSION_PREFIX . CustomerSsoLoginController::CLIENT_KEY. */
    private const STATE_KEY = 'sso_state:customer';

    private const STATE = 'a-valid-state-value';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.sso.url', 'https://sso.test');
        Config::set('services.sso.public_url', 'https://sso.test');
        Config::set('services.sso.customer_client_id', 'customer-client');
        Config::set('services.sso.customer_client_secret', 'customer-secret');
        Config::set('services.crm.url', 'https://crm.test');
        Config::set('services.crm.internal_url', 'https://crm.test');
        Config::set('services.crm.company_slug', 'test-garage');
    }

    public function test_redirect_sends_user_to_sso_authorize(): void
    {
        $this->get(route('customer.login'))
            ->assertRedirect()
            ->assertRedirectContains('https://sso.test/oauth/authorize')
            ->assertRedirectContains('client_id=customer-client');
    }

    public function test_redirect_includes_a_state_parameter_and_stores_it(): void
    {
        $response = $this->get(route('customer.login'));

        $state = session(self::STATE_KEY);

        $this->assertIsString($state);
        $this->assertNotSame('', $state);
        $response->assertRedirectContains('state=' . $state);
    }

    public function test_redirect_renders_setup_page_when_config_missing(): void
    {
        Config::set('services.sso.customer_client_id', null);
        Config::set('services.sso.customer_client_secret', null);

        $this->get(route('customer.login'))
            ->assertOk()
            ->assertInertia(
                fn ($p) => $p
                    ->component('Auth/CustomerSsoSetup')
                    ->where('missing', ['SSO_CUSTOMER_CLIENT_ID', 'SSO_CUSTOMER_CLIENT_SECRET']),
            );
    }

    public function test_callback_creates_customer_and_links_to_crm_on_match(): void
    {
        $this->fakeSsoAndCrm(crmCustomer: ['id' => 'crm-jane', 'name' => 'Jane D.', 'email' => 'jane@example.com', 'phone' => null]);

        $this->withSession([self::STATE_KEY => self::STATE])
            ->get(route('customer.callback', ['code' => 'authcode', 'state' => self::STATE]))
            ->assertRedirect(route('customer.dashboard'));

        $this->assertDatabaseHas('customers', [
            'email' => 'jane@example.com',
            'crm_customer_id' => 'crm-jane',
            'name' => 'Jane D.',
        ]);
    }

    public function test_callback_still_logs_in_when_crm_exchange_fails(): void
    {
        Http::fake([
            'https://sso.test/oauth/token' => Http::response(['access_token' => 'tok'], 200),
            'https://sso.test/oauth/userinfo' => Http::response([
                'sub' => 'sso-noone',
                'email' => 'noone@example.com',
                'name' => 'No One',
            ], 200),
            'https://crm.test/api/v1/auth/customer/*/sso/exchange' => Http::response(['message' => 'nope'], 401),
        ]);

        $this->withSession([self::STATE_KEY => self::STATE])
            ->get(route('customer.callback', ['code' => 'authcode', 'state' => self::STATE]))
            ->assertRedirect(route('customer.dashboard'));

        $this->assertDatabaseHas('customers', [
            'email' => 'noone@example.com',
            'crm_customer_id' => null,
        ]);
    }

    /**
     * Login CSRF guard. Without `state` the callback accepts any `?code=` the
     * victim's browser can be made to visit, logging them in as — and binding
     * them to the CRM customer of — whoever supplied the code.
     */
    public function test_callback_rejects_missing_state(): void
    {
        $this->fakeSsoAndCrm();

        $this->get(route('customer.callback', ['code' => 'authcode']))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('sso');

        $this->assertGuest('customer');
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        $this->fakeSsoAndCrm();

        $this->withSession([self::STATE_KEY => self::STATE])
            ->get(route('customer.callback', ['code' => 'authcode', 'state' => 'attacker-supplied']))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('sso');

        $this->assertGuest('customer');
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_state_is_single_use(): void
    {
        $this->fakeSsoAndCrm();

        $this->withSession([self::STATE_KEY => self::STATE])
            ->get(route('customer.callback', ['code' => 'authcode', 'state' => self::STATE]))
            ->assertRedirect(route('customer.dashboard'));

        // Same session, same state, replayed — must not authenticate again.
        $this->get(route('customer.callback', ['code' => 'authcode', 'state' => self::STATE]))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('sso');
    }

    public function test_callback_redirects_to_login_when_token_exchange_fails(): void
    {
        Http::fake([
            'https://sso.test/oauth/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->withSession([self::STATE_KEY => self::STATE])
            ->get(route('customer.callback', ['code' => 'bad', 'state' => self::STATE]))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('sso');
    }

    public function test_callback_rejects_missing_code(): void
    {
        $this->get(route('customer.callback'))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('sso');
    }

    public function test_callback_session_persists_through_to_dashboard(): void
    {
        // N1 from playbook audit: every other test uses actingAs(), which
        // bypasses the session driver. The four bugs surfaced in playbook 8
        // (remember_token, sessions.user_id bigint, Home.tsx link drift,
        // redirectGuestsTo target) only fire on the real cookie path:
        // callback → login() → DB write → redirect → re-read cookie. Lock
        // that path with one explicit test.
        $this->fakeSsoAndCrm(crmCustomer: ['id' => 'crm-session', 'name' => 'Session U.', 'email' => 'session@example.com', 'phone' => null]);

        $this->withSession([self::STATE_KEY => self::STATE])
            ->get(route('customer.callback', ['code' => 'authcode', 'state' => self::STATE]))
            ->assertRedirect(route('customer.dashboard'));

        // Same TestCase instance, same session — if the customer guard fails
        // to persist, this hop redirects to customer.login and fails.
        $this->get(route('customer.dashboard'))->assertOk();
    }

    public function test_logout_invalidates_session(): void
    {
        $customer = Customer::create([
            'email' => 'jane@example.com',
            'name' => 'Jane',
            'crm_customer_id' => 'crm-jane',
        ]);

        // Inertia::location only returns 409 with X-Inertia-Location when the
        // request carries the X-Inertia header; plain POST gets a 302 redirect.
        // We only assert the guard side-effect.
        $this->actingAs($customer, 'customer')
            ->post(route('customer.logout'));

        $this->assertGuest('customer');
    }

    /**
     * @param  array{id: string, name: ?string, email: ?string, phone: ?string}|null  $crmCustomer
     */
    private function fakeSsoAndCrm(?array $crmCustomer = null): void
    {
        $crmCustomer ??= ['id' => 'crm-jane', 'name' => 'Jane D.', 'email' => 'jane@example.com', 'phone' => null];

        Http::fake([
            'https://sso.test/oauth/token' => Http::response(['access_token' => 'tok'], 200),
            'https://sso.test/oauth/userinfo' => Http::response([
                'sub' => 'sso-jane',
                'email' => 'jane@example.com',
                'name' => 'Jane Doe',
            ], 200),
            // The real CRM contract: POST /api/v1/auth/customer/{slug}/sso/exchange
            // → {data: {token, expires_in, customer: {id, name, email, phone}}}
            'https://crm.test/api/v1/auth/customer/*/sso/exchange' => Http::response([
                'data' => [
                    'token' => 'crm-jwt',
                    'expires_in' => 3600,
                    'customer' => $crmCustomer,
                ],
            ], 200),
        ]);
    }
}
