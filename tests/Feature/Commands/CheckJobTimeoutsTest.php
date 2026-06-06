<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\CheckJobTimeouts;
use App\Models\ApprovalEvent;
use App\Models\Garage;
use App\Models\JobStateTransition;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckJobTimeoutsTest extends TestCase
{
    use RefreshDatabase;

    private Garage $garage;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*/api/v1/internal/notifications' => Http::response(['status' => 'ok'], 200)]);

        $this->garage = Garage::create([
            'name' => 'Test Garage',
            'slug' => 'test-garage',
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);

        $user = User::factory()->create();

        Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_GARAGE_ADMIN,
            'is_active' => true,
        ]);

        $this->vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'crm_customer_id' => 'crm-test-001',
            'registration' => 'AB12 CDE',
            'make' => 'Ford',
            'model' => 'Focus',
        ]);
    }

    public function test_alerts_job_stuck_in_awaiting_approval(): void
    {
        $job = $this->createJobInState(RepairJob::STATE_AWAITING_APPROVAL, hoursAgo: 25);

        $this->artisan(CheckJobTimeouts::class)->assertSuccessful();

        $this->assertDatabaseHas('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_TIMEOUT_ALERT,
            'actor_type' => ApprovalEvent::ACTOR_SYSTEM,
        ]);

        Http::assertSentCount(1);
    }

    public function test_skips_job_entered_state_less_than_24_hours_ago(): void
    {
        $this->createJobInState(RepairJob::STATE_AWAITING_APPROVAL, hoursAgo: 12);

        $this->artisan(CheckJobTimeouts::class)->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_skips_job_already_alerted_after_last_transition(): void
    {
        $job = $this->createJobInState(RepairJob::STATE_AWAITING_APPROVAL, hoursAgo: 30);

        ApprovalEvent::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'job_id' => $job->id,
            'actor_type' => ApprovalEvent::ACTOR_SYSTEM,
            'actor_id' => 'system',
            'event_type' => ApprovalEvent::EVENT_TIMEOUT_ALERT,
            'payload' => [],
            'occurred_at' => now()->subHours(20),
        ]);

        $this->artisan(CheckJobTimeouts::class)->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_alerts_job_stuck_in_awaiting_collection(): void
    {
        $job = $this->createJobInState(RepairJob::STATE_AWAITING_COLLECTION, hoursAgo: 25);

        $this->artisan(CheckJobTimeouts::class)->assertSuccessful();

        $this->assertDatabaseHas('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_TIMEOUT_ALERT,
        ]);
    }

    public function test_non_timeout_states_are_ignored(): void
    {
        $this->createJobInState(RepairJob::STATE_IN_PROGRESS, hoursAgo: 48);
        $this->createJobInState(RepairJob::STATE_APPROVED, hoursAgo: 48);

        $this->artisan(CheckJobTimeouts::class)->assertSuccessful();

        Http::assertNothingSent();
    }

    private function createJobInState(string $state, int $hoursAgo): RepairJob
    {
        $job = RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $this->garage->id,
            'vehicle_id' => $this->vehicle->id,
            'state' => $state,
        ]);

        JobStateTransition::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'job_id' => $job->id,
            'from_state' => RepairJob::STATE_IN_PROGRESS,
            'to_state' => $state,
            'transitioned_by' => 'system',
            'occurred_at' => now()->subHours($hoursAgo),
        ]);

        return $job;
    }
}
