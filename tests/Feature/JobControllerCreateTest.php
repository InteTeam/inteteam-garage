<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class JobControllerCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'en']], 200),
        ]);
    }

    public function test_admin_can_view_create_form(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->get(route('jobs.create'))
            ->assertOk();
    }

    public function test_non_admin_mechanic_cannot_view_create_form(): void
    {
        [$garage, $user] = $this->makeGarageWithMechanic(Mechanic::ROLE_MECHANIC);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->get(route('jobs.create'))
            ->assertForbidden();
    }

    public function test_store_creates_job_and_syncs_mechanic_pivot(): void
    {
        [$garage, $admin, $adminMechanic] = $this->makeGarageWithAdminMechanic();
        $vehicle = $this->makeVehicle($garage);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.store'), [
                'vehicle_id' => $vehicle->id,
                'mechanic_ids' => [$adminMechanic->id],
            ])
            ->assertRedirect(route('jobs.index'));

        $job = RepairJob::withoutGlobalScopes()->first();
        $this->assertNotNull($job);
        $this->assertSame($vehicle->id, $job->vehicle_id);
        $this->assertDatabaseHas('repair_job_mechanic', [
            'repair_job_id' => $job->id,
            'mechanic_id' => $adminMechanic->id,
        ]);
    }

    public function test_store_rejects_when_no_mechanic_assigned(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();
        $vehicle = $this->makeVehicle($garage);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.store'), [
                'vehicle_id' => $vehicle->id,
                'mechanic_ids' => [],
            ])
            ->assertSessionHasErrors('mechanic_ids');

        $this->assertDatabaseCount('repair_jobs', 0);
    }

    public function test_store_rejects_mechanic_from_other_garage(): void
    {
        [$garageA, $adminA] = $this->makeGarageWithAdminMechanic();
        [$garageB, , $mechanicB] = $this->makeGarageWithAdminMechanic();
        $vehicleA = $this->makeVehicle($garageA);

        $this->actingAs($adminA)
            ->withSession(['current_garage_id' => $garageA->id])
            ->post(route('jobs.store'), [
                'vehicle_id' => $vehicleA->id,
                'mechanic_ids' => [$mechanicB->id],
            ])
            ->assertSessionHasErrors('mechanic_ids.0');
    }

    public function test_store_rejects_vehicle_from_other_garage(): void
    {
        [$garageA, $adminA, $adminMechanicA] = $this->makeGarageWithAdminMechanic();
        [$garageB] = $this->makeGarageWithAdminMechanic();
        $vehicleB = $this->makeVehicle($garageB);

        $this->actingAs($adminA)
            ->withSession(['current_garage_id' => $garageA->id])
            ->post(route('jobs.store'), [
                'vehicle_id' => $vehicleB->id,
                'mechanic_ids' => [$adminMechanicA->id],
            ])
            ->assertSessionHasErrors('vehicle_id');

        $this->assertDatabaseCount('repair_jobs', 0);
    }

    /** @return array{0: Garage, 1: User} */
    private function makeGarageWithAdmin(): array
    {
        return $this->makeGarageWithMechanic(Mechanic::ROLE_GARAGE_ADMIN);
    }

    /** @return array{0: Garage, 1: User, 2: Mechanic} */
    private function makeGarageWithAdminMechanic(): array
    {
        $garage = $this->makeGarage();
        $user = User::factory()->create();
        $mechanic = Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_GARAGE_ADMIN,
            'is_active' => true,
        ]);

        return [$garage, $user, $mechanic];
    }

    /** @return array{0: Garage, 1: User} */
    private function makeGarageWithMechanic(string $role): array
    {
        $garage = $this->makeGarage();
        $user = User::factory()->create();
        Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return [$garage, $user];
    }

    private function makeGarage(): Garage
    {
        return Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);
    }

    private function makeVehicle(Garage $garage): Vehicle
    {
        return Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-' . uniqid(),
            'registration' => strtoupper(substr(uniqid(), -7)),
            'make' => 'Ford',
            'model' => 'Focus',
        ]);
    }
}
