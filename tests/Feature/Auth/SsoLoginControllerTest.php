<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SsoLoginControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Must match SsoService::STATE_SESSION_PREFIX . SsoLoginController::CLIENT_KEY. */
    private const STATE_KEY = 'sso_state:mechanic';

    private const STATE = 'mechanic-state-value';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.sso.url', 'https://sso.test');
        Config::set('services.sso.public_url', 'https://sso.test');
        Config::set('services.sso.client_id', 'mechanic-client');
        Config::set('services.sso.client_secret', 'mechanic-secret');
    }

    public function test_redirect_includes_a_state_parameter_and_stores_it(): void
    {
        $response = $this->get(route('login'));

        $state = session(self::STATE_KEY);

        $this->assertIsString($state);
        $response
            ->assertRedirectContains('https://sso.test/oauth/authorize')
            ->assertRedirectContains('client_id=mechanic-client')
            ->assertRedirectContains('state=' . $state);
    }

    public function test_redirect_renders_setup_page_when_config_missing(): void
    {
        Config::set('services.sso.client_id', null);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Auth/SsoSetup'));
    }

    /**
     * The two SSO clients share one browser session. State is namespaced per
     * client so opening both flows in two tabs does not have the second redirect
     * clobber the first's state and break the first callback.
     */
    public function test_state_keys_are_namespaced_per_client(): void
    {
        Config::set('services.sso.customer_client_id', 'customer-client');
        Config::set('services.sso.customer_client_secret', 'customer-secret');

        $this->get(route('login'));
        $mechanicState = session(self::STATE_KEY);

        $this->get(route('customer.login'));

        $this->assertSame($mechanicState, session(self::STATE_KEY));
        $this->assertNotSame($mechanicState, session('sso_state:customer'));
    }

    public function test_callback_logs_in_mechanic_and_sets_garage_context(): void
    {
        [$garage, $user] = $this->createGarageWithMechanic();
        $this->fakeSso();

        $this->withSession([self::STATE_KEY => self::STATE])
            ->get(route('auth.callback', ['code' => 'authcode', 'state' => self::STATE]))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame($garage->id, session('current_garage_id'));
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        $this->createGarageWithMechanic();
        $this->fakeSso();

        $this->withSession([self::STATE_KEY => self::STATE])
            ->get(route('auth.callback', ['code' => 'authcode', 'state' => 'forged']))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('sso');

        $this->assertGuest();
    }

    public function test_callback_rejects_missing_state(): void
    {
        $this->createGarageWithMechanic();
        $this->fakeSso();

        $this->get(route('auth.callback', ['code' => 'authcode']))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('sso');

        $this->assertGuest();
    }

    /**
     * `crm_user_id` was never written, so `CrmStaffNotificationService` skipped
     * every staff notification with "mechanic has no crm_user_id mapping" — the
     * whole staff-alert feature was inert. SSO `sub` is the ecosystem-wide user
     * identity the CRM's `recipient_type=staff` expects.
     */
    public function test_callback_persists_sso_sub_as_crm_user_id(): void
    {
        [, $user] = $this->createGarageWithMechanic();
        $this->fakeSso();

        $this->withSession([self::STATE_KEY => self::STATE])
            ->get(route('auth.callback', ['code' => 'authcode', 'state' => self::STATE]));

        $this->assertSame('sso-user-123', $user->fresh()->crm_user_id);
    }

    public function test_callback_binds_garage_to_crm_company_from_claims(): void
    {
        [$garage] = $this->createGarageWithMechanic();
        $this->assertNull($garage->crm_company_id);

        $this->fakeSso();

        $this->withSession([self::STATE_KEY => self::STATE])
            ->get(route('auth.callback', ['code' => 'authcode', 'state' => self::STATE]));

        $this->assertSame('crm-company-abc', $garage->fresh()->crm_company_id);
    }

    public function test_callback_does_not_overwrite_an_existing_crm_company(): void
    {
        [$garage] = $this->createGarageWithMechanic();
        $garage->forceFill(['crm_company_id' => 'set-by-operator'])->save();

        $this->fakeSso();

        $this->withSession([self::STATE_KEY => self::STATE])
            ->get(route('auth.callback', ['code' => 'authcode', 'state' => self::STATE]));

        $this->assertSame('set-by-operator', $garage->fresh()->crm_company_id);
    }

    public function test_callback_rejects_user_without_a_mechanic_record(): void
    {
        $this->fakeSso();

        $this->withSession([self::STATE_KEY => self::STATE])
            ->get(route('auth.callback', ['code' => 'authcode', 'state' => self::STATE]))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('sso');

        $this->assertGuest();
    }

    private function fakeSso(): void
    {
        Http::fake([
            'https://sso.test/oauth/token' => Http::response(['access_token' => 'tok'], 200),
            'https://sso.test/oauth/userinfo' => Http::response([
                'sub' => 'sso-user-123',
                'email' => 'mech@example.com',
                'name' => 'Mech Anic',
                'company_id' => 'crm-company-abc',
                'company_name' => 'Test Garage Ltd',
            ], 200),
        ]);
    }

    /**
     * @return array{0: Garage, 1: User}
     */
    private function createGarageWithMechanic(): array
    {
        $garage = Garage::create([
            'name' => 'SSO Garage',
            'slug' => 'sso-garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => Garage::CHANNEL_EMAIL,
            'locale' => 'en',
        ]);

        $user = User::factory()->create(['email' => 'mech@example.com']);

        Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_GARAGE_ADMIN,
            'is_active' => true,
        ]);

        return [$garage, $user];
    }
}
