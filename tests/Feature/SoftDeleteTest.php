<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_mechanic_destroy_soft_deletes(): void
    {
        [$garage, $admin] = $this->setupAdmin();

        $targetUser = User::factory()->create();
        $target = Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $targetUser->id,
            'role' => Mechanic::ROLE_MECHANIC,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->delete(route('mechanics.destroy', $target->id))
            ->assertRedirect();

        $this->assertSoftDeleted('mechanics', ['id' => $target->id]);
    }

    public function test_vehicle_destroy_soft_deletes(): void
    {
        [$garage, $admin] = $this->setupAdmin();

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-sd-001',
            'registration' => 'SD12 EL1',
            'make' => 'Ford',
            'model' => 'Mondeo',
        ]);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->delete(route('vehicles.destroy', $vehicle->id))
            ->assertRedirect();

        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_estimate_destroy_soft_deletes(): void
    {
        [$garage, $admin] = $this->setupAdmin();
        $job = $this->makeJob($garage);

        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'revision_number' => 1,
        ]);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->delete(route('jobs.estimates.destroy', ['job' => $job->id, 'estimate' => $estimate->id]))
            ->assertRedirect();

        $this->assertSoftDeleted('estimates', ['id' => $estimate->id]);
    }

    /**
     * @return array{0: Garage, 1: User}
     */
    private function setupAdmin(): array
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

    private function makeJob(Garage $garage): RepairJob
    {
        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-sd-job-' . uniqid(),
            'registration' => 'SD22 ABC',
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        return RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_IN_PROGRESS,
        ]);
    }
}
