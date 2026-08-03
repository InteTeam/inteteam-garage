<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class VehicleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These cases exercise the CRM existence check, so the lookup-by-id
        // capability is switched on explicitly. It is OFF by default because the
        // CRM does not expose the endpoint yet and a missing route answers 404 —
        // indistinguishable from "customer not found", which would reject every
        // valid id. `test_store_accepts_unverifiable_customer_when_lookup_disabled`
        // covers the default.
        Config::set('services.crm.customer_lookup_by_id_enabled', true);

        Http::fake([
            '*/api/v1/internal/customers/crm-valid' => Http::response(['data' => ['id' => 'crm-valid', 'locale' => 'en']], 200),
            '*/api/v1/internal/customers/crm-missing' => Http::response(['error' => 'not found'], 404),
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'en']], 200),
        ]);
    }

    /**
     * Regression guard for the default posture: with the capability off, a CRM
     * 404 must not fail validation. Before the flag existed this is exactly the
     * path that made vehicle creation impossible in every CRM-configured
     * environment.
     */
    public function test_store_accepts_unverifiable_customer_when_lookup_disabled(): void
    {
        Config::set('services.crm.customer_lookup_by_id_enabled', false);

        [$garage, $admin] = $this->makeGarageWithAdmin();

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('vehicles.store'), [
                'crm_customer_id' => 'crm-missing',
                'registration' => 'ZZ99 ZZZ',
                'make' => 'Ford',
                'model' => 'Focus',
                'year' => 2020,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vehicles', [
            'registration' => 'ZZ99 ZZZ',
            'crm_customer_id' => 'crm-missing',
        ]);
    }

    public function test_admin_can_view_create_form(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->get(route('vehicles.create'))
            ->assertOk();
    }

    public function test_non_admin_mechanic_cannot_view_create_form(): void
    {
        [$garage, $user] = $this->makeGarageWithMechanic(Mechanic::ROLE_MECHANIC);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->get(route('vehicles.create'))
            ->assertForbidden();
    }

    public function test_admin_can_store_valid_vehicle(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('vehicles.store'), [
                'crm_customer_id' => 'crm-valid',
                'registration' => 'AB12 CDE',
                'make' => 'Ford',
                'model' => 'Focus',
                'year' => 2020,
                'colour' => 'Blue',
            ])
            ->assertRedirect(route('vehicles.index'));

        $this->assertDatabaseHas('vehicles', [
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-valid',
            'registration' => 'AB12 CDE',
        ]);
    }

    public function test_store_rejects_missing_crm_customer_id(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('vehicles.store'), [
                'registration' => 'AB12 CDE',
                'make' => 'Ford',
                'model' => 'Focus',
                'year' => 2020,
            ])
            ->assertSessionHasErrors('crm_customer_id');

        $this->assertDatabaseMissing('vehicles', ['registration' => 'AB12 CDE']);
    }

    public function test_store_rejects_crm_customer_not_found_in_crm(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('vehicles.store'), [
                'crm_customer_id' => 'crm-missing',
                'registration' => 'AB12 CDE',
                'make' => 'Ford',
                'model' => 'Focus',
                'year' => 2020,
            ])
            ->assertSessionHasErrors('crm_customer_id');

        $this->assertDatabaseMissing('vehicles', ['registration' => 'AB12 CDE']);
    }

    public function test_store_rejects_year_out_of_bounds(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('vehicles.store'), [
                'crm_customer_id' => 'crm-valid',
                'registration' => 'AB12 CDE',
                'make' => 'Ford',
                'model' => 'Focus',
                'year' => 1850,
            ])
            ->assertSessionHasErrors('year');
    }

    public function test_admin_can_view_edit_form(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();
        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-1',
            'registration' => 'XY99 ZZZ',
            'make' => 'Toyota',
            'model' => 'Yaris',
        ]);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->get(route('vehicles.edit', $vehicle->id))
            ->assertOk();
    }

    public function test_show_renders_for_admin(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();
        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-1',
            'registration' => 'SH01 OWN',
            'make' => 'Skoda',
            'model' => 'Fabia',
        ]);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->get(route('vehicles.show', $vehicle->id))
            ->assertOk();
    }

    /** @return array{0: Garage, 1: User} */
    private function makeGarageWithAdmin(): array
    {
        return $this->makeGarageWithMechanic(Mechanic::ROLE_GARAGE_ADMIN);
    }

    /** @return array{0: Garage, 1: User} */
    private function makeGarageWithMechanic(string $role): array
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);
        $user = User::factory()->create();
        Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return [$garage, $user];
    }
}
