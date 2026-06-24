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

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.sso.url', 'https://sso.test');
        Config::set('services.sso.public_url', 'https://sso.test');
        Config::set('services.sso.customer_client_id', 'customer-client');
        Config::set('services.sso.customer_client_secret', 'customer-secret');
    }

    public function test_redirect_sends_user_to_sso_authorize(): void
    {
        $this->get(route('customer.login'))
            ->assertRedirect()
            ->assertRedirectContains('https://sso.test/oauth/authorize')
            ->assertRedirectContains('client_id=customer-client');
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
        Http::fake([
            'https://sso.test/oauth/token' => Http::response(['access_token' => 'tok'], 200),
            'https://sso.test/oauth/userinfo' => Http::response([
                'email' => 'jane@example.com',
                'name' => 'Jane Doe',
            ], 200),
            '*/api/v1/internal/customers?*' => Http::response([
                'data' => ['id' => 'crm-jane', 'name' => 'Jane D.'],
            ], 200),
        ]);

        $this->get(route('customer.callback', ['code' => 'authcode']))
            ->assertRedirect(route('customer.dashboard'));

        $this->assertDatabaseHas('customers', [
            'email' => 'jane@example.com',
            'crm_customer_id' => 'crm-jane',
            'name' => 'Jane D.',
        ]);
    }

    public function test_callback_still_logs_in_when_crm_does_not_recognise_email(): void
    {
        Http::fake([
            'https://sso.test/oauth/token' => Http::response(['access_token' => 'tok'], 200),
            'https://sso.test/oauth/userinfo' => Http::response([
                'email' => 'noone@example.com',
                'name' => 'No One',
            ], 200),
            '*/api/v1/internal/customers?*' => Http::response(null, 404),
        ]);

        $this->get(route('customer.callback', ['code' => 'authcode']))
            ->assertRedirect(route('customer.dashboard'));

        $this->assertDatabaseHas('customers', [
            'email' => 'noone@example.com',
            'crm_customer_id' => null,
        ]);
    }

    public function test_callback_redirects_to_login_when_token_exchange_fails(): void
    {
        Http::fake([
            'https://sso.test/oauth/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->get(route('customer.callback', ['code' => 'bad']))
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
        Http::fake([
            'https://sso.test/oauth/token' => Http::response(['access_token' => 'tok'], 200),
            'https://sso.test/oauth/userinfo' => Http::response([
                'email' => 'session@example.com',
                'name' => 'Session User',
            ], 200),
            '*/api/v1/internal/customers?*' => Http::response([
                'data' => ['id' => 'crm-session', 'name' => 'Session U.'],
            ], 200),
        ]);

        $this->get(route('customer.callback', ['code' => 'authcode']))
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
}
