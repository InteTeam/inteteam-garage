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

final class MechanicAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Garage $garage;

    private RepairJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->garage = Garage::create([
            'name' => 'Assignment Garage',
            'slug' => 'assignment-garage',
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'crm_customer_id' => 'crm-assign-1',
            'registration' => 'AS01 IGN',
            'make' => 'Ford',
            'model' => 'Transit',
        ]);

        $this->job = RepairJob::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_CREATED,
        ]);
    }

    public function test_single_mechanic_can_be_assigned_to_job(): void
    {
        $mechanic = $this->makeMechanic();

        $this->job->mechanics()->attach($mechanic->id);

        $this->assertCount(1, $this->job->mechanics()->get());
        $this->assertDatabaseHas('repair_job_mechanic', [
            'repair_job_id' => $this->job->id,
            'mechanic_id' => $mechanic->id,
        ]);
    }

    public function test_multiple_mechanics_can_be_assigned_to_one_job(): void
    {
        $mechanics = collect(range(1, 3))->map(fn (int $i) => $this->makeMechanic("user-{$i}"));

        $this->job->mechanics()->sync($mechanics->pluck('id')->all());

        $this->assertCount(3, $this->job->mechanics()->get());
        foreach ($mechanics as $mechanic) {
            $this->assertDatabaseHas('repair_job_mechanic', [
                'repair_job_id' => $this->job->id,
                'mechanic_id' => $mechanic->id,
            ]);
        }
    }

    public function test_sync_replaces_existing_assignments(): void
    {
        $first = $this->makeMechanic('user-replace-a');
        $second = $this->makeMechanic('user-replace-b');

        $this->job->mechanics()->attach($first->id);
        $this->assertCount(1, $this->job->mechanics()->get());

        $this->job->mechanics()->sync([$second->id]);

        $reloaded = $this->job->mechanics()->get();
        $this->assertCount(1, $reloaded);
        $this->assertEquals($second->id, $reloaded->first()->id);
        $this->assertDatabaseMissing('repair_job_mechanic', [
            'repair_job_id' => $this->job->id,
            'mechanic_id' => $first->id,
        ]);
    }

    public function test_mechanic_can_be_detached_without_affecting_others(): void
    {
        $kept = $this->makeMechanic('user-kept');
        $removed = $this->makeMechanic('user-removed');

        $this->job->mechanics()->attach([$kept->id, $removed->id]);
        $this->assertCount(2, $this->job->mechanics()->get());

        $this->job->mechanics()->detach($removed->id);

        $remaining = $this->job->mechanics()->get();
        $this->assertCount(1, $remaining);
        $this->assertEquals($kept->id, $remaining->first()->id);
    }

    public function test_all_mechanics_in_garage_can_be_assigned_to_one_job(): void
    {
        $allIds = collect(range(1, 5))
            ->map(fn (int $i) => $this->makeMechanic("user-all-{$i}")->id)
            ->all();

        $this->job->mechanics()->sync($allIds);

        $this->assertCount(5, $this->job->mechanics()->get());
        $this->assertCount(
            5,
            Mechanic::withoutGlobalScopes()
                ->where('garage_id', $this->garage->id)
                ->whereIn('id', $allIds)
                ->get()
        );
    }

    public function test_assigning_same_mechanic_twice_is_rejected_by_composite_pk(): void
    {
        $mechanic = $this->makeMechanic('user-dup');

        $this->job->mechanics()->attach($mechanic->id);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $this->job->mechanics()->attach($mechanic->id);
    }

    public function test_mechanic_can_be_assigned_to_multiple_jobs(): void
    {
        $mechanic = $this->makeMechanic('user-multi-job');

        $secondJob = RepairJob::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'vehicle_id' => $this->job->vehicle_id,
            'state' => RepairJob::STATE_CREATED,
        ]);

        $this->job->mechanics()->attach($mechanic->id);
        $secondJob->mechanics()->attach($mechanic->id);

        $assignments = $mechanic->repairJobs()->get();
        $this->assertCount(2, $assignments);
        $this->assertTrue($assignments->contains('id', $this->job->id));
        $this->assertTrue($assignments->contains('id', $secondJob->id));
    }

    public function test_deleting_repair_job_cascades_pivot_rows(): void
    {
        $mechanic = $this->makeMechanic('user-cascade-job');
        $this->job->mechanics()->attach($mechanic->id);

        $this->assertDatabaseHas('repair_job_mechanic', [
            'repair_job_id' => $this->job->id,
            'mechanic_id' => $mechanic->id,
        ]);

        $this->job->forceDelete();

        $this->assertDatabaseMissing('repair_job_mechanic', [
            'repair_job_id' => $this->job->id,
            'mechanic_id' => $mechanic->id,
        ]);
    }

    public function test_deleting_mechanic_cascades_pivot_rows(): void
    {
        $mechanic = $this->makeMechanic('user-cascade-mech');
        $this->job->mechanics()->attach($mechanic->id);

        $this->assertDatabaseHas('repair_job_mechanic', [
            'repair_job_id' => $this->job->id,
            'mechanic_id' => $mechanic->id,
        ]);

        $mechanic->forceDelete();

        $this->assertDatabaseMissing('repair_job_mechanic', [
            'repair_job_id' => $this->job->id,
            'mechanic_id' => $mechanic->id,
        ]);
    }

    private function makeMechanic(?string $suffix = null): Mechanic
    {
        $user = User::factory()->create(
            $suffix !== null ? ['email' => "{$suffix}@example.test"] : []
        );

        return Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_MECHANIC,
            'is_active' => true,
        ]);
    }
}
