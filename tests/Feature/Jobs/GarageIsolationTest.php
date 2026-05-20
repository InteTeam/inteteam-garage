<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GarageIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mechanic_cannot_access_job_from_another_garage(): void
    {
        [$garage1, $user1] = $this->createGarageWithAdmin();
        [$garage2, $user2] = $this->createGarageWithAdmin();

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage2->id,
            'crm_customer_id' => 'crm-456',
            'registration' => 'XY99 ZAB',
            'make' => 'BMW',
            'model' => '3 Series',
        ]);

        $job = RepairJob::withoutGlobalScopes()->create([
            'garage_id' => $garage2->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_CREATED,
        ]);

        $this->actingAs($user1);
        session(['current_garage_id' => $garage1->id]);

        $response = $this->get("/jobs/{$job->id}");
        $response->assertNotFound();
    }

    public function test_mechanic_can_access_own_garage_job(): void
    {
        [$garage, $user] = $this->createGarageWithAdmin();

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-789',
            'registration' => 'AB12 XYZ',
            'make' => 'Vauxhall',
            'model' => 'Astra',
        ]);

        RepairJob::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_CREATED,
        ]);

        $this->actingAs($user);
        session(['current_garage_id' => $garage->id]);

        $response = $this->get('/jobs');
        $response->assertOk();
    }

    private function createGarageWithAdmin(): array
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
            'role' => Mechanic::ROLE_GARAGE_ADMIN,
            'is_active' => true,
        ]);

        return [$garage, $user];
    }
}
