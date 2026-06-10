<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\Garage;
use App\Models\JobStage;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class JobStageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'en']], 200),
            'https://api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'en']]],
            ], 200),
        ]);
    }

    public function test_store_persists_stage_under_job_from_route(): void
    {
        [$garage, $user] = $this->makeGarageWithAdmin();
        $job = $this->makeJob($garage);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.stages.store', $job->id), [
                'name' => JobStage::STAGE_PRE_INSPECTION,
                'sort_order' => 1,
                'locked_at' => now()->toIso8601String(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('job_stages', [
            'job_id' => $job->id,
            'garage_id' => $garage->id,
            'name' => JobStage::STAGE_PRE_INSPECTION,
            'sort_order' => 1,
        ]);
    }

    public function test_destroy_resolves_nested_job_and_stage_params(): void
    {
        [$garage, $user] = $this->makeGarageWithAdmin();
        $job = $this->makeJob($garage);
        $stage = $this->makeStage($job);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->delete(route('jobs.stages.destroy', ['job' => $job->id, 'stage' => $stage->id]))
            ->assertRedirect();

        $this->assertSoftDeleted('job_stages', ['id' => $stage->id]);
    }

    public function test_update_rejects_stage_from_different_job(): void
    {
        [$garage, $user] = $this->makeGarageWithAdmin();
        $jobA = $this->makeJob($garage);
        $jobB = $this->makeJob($garage);
        $stageOnB = $this->makeStage($jobB);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->put(route('jobs.stages.update', ['job' => $jobA->id, 'stage' => $stageOnB->id]), [
                'name' => 'tampered',
            ])
            ->assertStatus(500);

        $this->assertDatabaseHas('job_stages', [
            'id' => $stageOnB->id,
            'job_id' => $jobB->id,
            'name' => JobStage::STAGE_PRE_INSPECTION,
        ]);
    }

    public function test_update_notes_persists_same_locale_without_translation(): void
    {
        [$garage, $user] = $this->makeGarageWithAdmin();
        $job = $this->makeJob($garage);
        $stage = $this->makeStage($job);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->patch(route('jobs.stages.notes.update', ['job' => $job->id, 'stage' => $stage->id]), [
                'notes' => 'Replaced brake pads on both front wheels.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('job_stages', [
            'id' => $stage->id,
            'notes' => 'Replaced brake pads on both front wheels.',
            'notes_target_locale' => null,
            'notes_translated' => null,
        ]);
    }

    public function test_update_notes_rejects_non_mechanic_user(): void
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);
        $orphanUser = User::factory()->create();
        $job = $this->makeJob($garage);
        $stage = $this->makeStage($job);

        $this->actingAs($orphanUser)
            ->withSession(['current_garage_id' => $garage->id])
            ->patch(route('jobs.stages.notes.update', ['job' => $job->id, 'stage' => $stage->id]), [
                'notes' => 'should not save',
            ])
            ->assertForbidden();
    }

    public function test_update_notes_rejects_stage_from_different_job(): void
    {
        [$garage, $user] = $this->makeGarageWithAdmin();
        $jobA = $this->makeJob($garage);
        $jobB = $this->makeJob($garage);
        $stageOnB = $this->makeStage($jobB);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->patch(route('jobs.stages.notes.update', ['job' => $jobA->id, 'stage' => $stageOnB->id]), [
                'notes' => 'cross-job tamper',
            ])
            ->assertStatus(500);

        $this->assertDatabaseHas('job_stages', [
            'id' => $stageOnB->id,
            'notes' => null,
        ]);
    }

    public function test_non_admin_mechanic_cannot_store_stage(): void
    {
        [$garage, $user] = $this->makeGarageWithMechanic(Mechanic::ROLE_MECHANIC);
        $job = $this->makeJob($garage);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.stages.store', $job->id), [
                'name' => JobStage::STAGE_PRE_INSPECTION,
                'sort_order' => 1,
                'locked_at' => now()->toIso8601String(),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('job_stages', ['job_id' => $job->id]);
    }

    /**
     * @return array{0: Garage, 1: User}
     */
    private function makeGarageWithAdmin(): array
    {
        return $this->makeGarageWithMechanic(Mechanic::ROLE_GARAGE_ADMIN);
    }

    /**
     * @return array{0: Garage, 1: User}
     */
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

    private function makeJob(Garage $garage): RepairJob
    {
        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-' . uniqid(),
            'registration' => Str::upper(Str::random(7)),
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        return RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_CREATED,
        ]);
    }

    private function makeStage(RepairJob $job): JobStage
    {
        return JobStage::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'name' => JobStage::STAGE_PRE_INSPECTION,
            'sort_order' => 1,
            'locked_at' => null,
        ]);
    }
}
