<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ComplianceSource;
use App\Enums\ComplianceType;
use App\Models\ComplianceRecord;
use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ComplianceRecordControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'en']], 200),
        ]);
    }

    public function test_admin_can_record_mot_expiry(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();
        $vehicle = $this->makeVehicle($garage->id);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('vehicles.compliance.store', $vehicle->id), [
                'type' => ComplianceType::MOT->value,
                'expires_on' => '2027-04-12',
                'note' => 'Booked at Quickfit',
            ])
            ->assertRedirect(route('vehicles.show', $vehicle->id));

        $this->assertDatabaseHas('compliance_records', [
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'type' => ComplianceType::MOT->value,
            'source' => ComplianceSource::MANUAL->value,
            'expires_on' => '2027-04-12 00:00:00',
            'note' => 'Booked at Quickfit',
        ]);
    }

    public function test_non_admin_mechanic_cannot_record_compliance(): void
    {
        [$garage, $user] = $this->makeGarageWithMechanic(Mechanic::ROLE_MECHANIC);
        $vehicle = $this->makeVehicle($garage->id);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('vehicles.compliance.store', $vehicle->id), [
                'type' => ComplianceType::TAX->value,
                'expires_on' => '2027-01-01',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('compliance_records', 0);
    }

    public function test_cross_garage_vehicle_returns_not_found(): void
    {
        [$garageA, $adminA] = $this->makeGarageWithAdmin();
        [$garageB] = $this->makeGarageWithAdmin();
        $vehicleB = $this->makeVehicle($garageB->id);

        $this->actingAs($adminA)
            ->withSession(['current_garage_id' => $garageA->id])
            ->post(route('vehicles.compliance.store', $vehicleB->id), [
                'type' => ComplianceType::MOT->value,
                'expires_on' => '2027-04-12',
            ])
            ->assertNotFound();
    }

    public function test_refresh_cross_garage_vehicle_returns_not_found(): void
    {
        Config::set('services.dvla.ves_api_key', 'test-key');

        [$garageA, $adminA] = $this->makeGarageWithAdmin();
        [$garageB] = $this->makeGarageWithAdmin();
        $vehicleB = $this->makeVehicle($garageB->id);

        $this->actingAs($adminA)
            ->withSession(['current_garage_id' => $garageA->id])
            ->post(route('vehicles.compliance.refresh', $vehicleB->id))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_non_admin_mechanic_cannot_refresh_from_dvla(): void
    {
        Config::set('services.dvla.ves_api_key', 'test-key');

        [$garage, $user] = $this->makeGarageWithMechanic(Mechanic::ROLE_MECHANIC);
        $vehicle = $this->makeVehicle($garage->id);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('vehicles.compliance.refresh', $vehicle->id))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_invalid_type_is_rejected(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();
        $vehicle = $this->makeVehicle($garage->id);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('vehicles.compliance.store', $vehicle->id), [
                'type' => 'parking',
                'expires_on' => '2027-04-12',
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_missing_expires_on_is_rejected(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();
        $vehicle = $this->makeVehicle($garage->id);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('vehicles.compliance.store', $vehicle->id), [
                'type' => ComplianceType::INSURANCE->value,
            ])
            ->assertSessionHasErrors('expires_on');
    }

    public function test_show_payload_includes_latest_record_per_type(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();
        $vehicle = $this->makeVehicle($garage->id);

        ComplianceRecord::create([
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'recorded_by_user_id' => $admin->id,
            'type' => ComplianceType::MOT,
            'source' => ComplianceSource::MANUAL,
            'expires_on' => '2026-12-01',
        ]);

        // Newer MOT record — should be the "current" one.
        $newer = ComplianceRecord::create([
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'recorded_by_user_id' => $admin->id,
            'type' => ComplianceType::MOT,
            'source' => ComplianceSource::MANUAL,
            'expires_on' => '2027-12-01',
        ]);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->get(route('vehicles.show', $vehicle->id))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Vehicles/Show')
                    ->where('compliance.mot.id', $newer->id)
                    ->where('compliance.tax', null)
                    ->where('compliance.insurance', null)
                    ->has('complianceHistory', 2)
            );
    }

    public function test_refresh_returns_friendly_error_when_dvla_not_configured(): void
    {
        Config::set('services.dvla.ves_api_key', null);

        [$garage, $admin] = $this->makeGarageWithAdmin();
        $vehicle = $this->makeVehicle($garage->id);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('vehicles.compliance.refresh', $vehicle->id))
            ->assertRedirect(route('vehicles.show', $vehicle->id))
            ->assertSessionHas('type', 'error');

        $this->assertDatabaseCount('compliance_records', 0);
    }

    public function test_refresh_pulls_mot_and_tax_from_dvla(): void
    {
        Config::set('services.dvla.ves_url', 'https://dvla.test/vehicle-enquiry/v1/vehicles');
        Config::set('services.dvla.ves_api_key', 'test-key');

        Http::fake([
            'dvla.test/*' => Http::response([
                'registrationNumber' => 'AB12CDE',
                'motExpiryDate' => '2027-05-20',
                'motStatus' => 'Valid',
                'taxDueDate' => '2026-12-15',
                'taxStatus' => 'Taxed',
                'make' => 'FORD',
                'colour' => 'BLUE',
                'yearOfManufacture' => 2020,
                'fuelType' => 'PETROL',
            ], 200),
        ]);

        [$garage, $admin] = $this->makeGarageWithAdmin();
        $vehicle = $this->makeVehicle($garage->id);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('vehicles.compliance.refresh', $vehicle->id))
            ->assertRedirect(route('vehicles.show', $vehicle->id))
            ->assertSessionHas('type', 'success');

        $this->assertDatabaseHas('compliance_records', [
            'vehicle_id' => $vehicle->id,
            'type' => ComplianceType::MOT->value,
            'source' => ComplianceSource::DVLA->value,
            'expires_on' => '2027-05-20 00:00:00',
        ]);
        $this->assertDatabaseHas('compliance_records', [
            'vehicle_id' => $vehicle->id,
            'type' => ComplianceType::TAX->value,
            'source' => ComplianceSource::DVLA->value,
            'expires_on' => '2026-12-15 00:00:00',
        ]);
    }

    public function test_refresh_skips_records_when_expiry_unchanged(): void
    {
        Config::set('services.dvla.ves_url', 'https://dvla.test/vehicle-enquiry/v1/vehicles');
        Config::set('services.dvla.ves_api_key', 'test-key');

        Http::fake([
            'dvla.test/*' => Http::response([
                'registrationNumber' => 'AB12CDE',
                'motExpiryDate' => '2027-05-20',
                'motStatus' => 'Valid',
                'taxDueDate' => '2026-12-15',
                'taxStatus' => 'Taxed',
            ], 200),
        ]);

        [$garage, $admin] = $this->makeGarageWithAdmin();
        $vehicle = $this->makeVehicle($garage->id);

        // Seed with same dates DVLA will return.
        ComplianceRecord::create([
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'recorded_by_user_id' => $admin->id,
            'type' => ComplianceType::MOT,
            'source' => ComplianceSource::DVLA,
            'expires_on' => '2027-05-20',
        ]);
        ComplianceRecord::create([
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'recorded_by_user_id' => $admin->id,
            'type' => ComplianceType::TAX,
            'source' => ComplianceSource::DVLA,
            'expires_on' => '2026-12-15',
        ]);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('vehicles.compliance.refresh', $vehicle->id))
            ->assertRedirect();

        // Still 2 — no duplicates added since dates match.
        $this->assertDatabaseCount('compliance_records', 2);
    }

    public function test_refresh_handles_dvla_404_gracefully(): void
    {
        Config::set('services.dvla.ves_url', 'https://dvla.test/vehicle-enquiry/v1/vehicles');
        Config::set('services.dvla.ves_api_key', 'test-key');

        Http::fake([
            'dvla.test/*' => Http::response(['message' => 'Vehicle not found'], 404),
        ]);

        [$garage, $admin] = $this->makeGarageWithAdmin();
        $vehicle = $this->makeVehicle($garage->id);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('vehicles.compliance.refresh', $vehicle->id))
            ->assertRedirect()
            ->assertSessionHas('type', 'error');

        $this->assertDatabaseCount('compliance_records', 0);
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

    private function makeVehicle(string $garageId): Vehicle
    {
        return Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garageId,
            'crm_customer_id' => 'crm-1',
            'registration' => 'AB12 CDE',
            'make' => 'Ford',
            'model' => 'Focus',
        ]);
    }
}
