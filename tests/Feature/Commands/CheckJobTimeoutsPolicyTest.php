<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\ApprovalEvent;
use App\Models\Garage;
use App\Models\JobStateTransition;
use App\Models\Mechanic;
use App\Models\MechanicOnCall;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CheckJobTimeoutsPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/api/v1/internal/notifications' => Http::response(['status' => 'ok'], 200),
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'en']], 200),
        ]);
    }

    public function test_policy_24_7_dispatches_to_all_assigned_mechanics(): void
    {
        $garage = $this->makeGarage(Garage::TIMEOUT_POLICY_24_7);
        [$job, $mech1, $mech2] = $this->makeTimedOutJobWithTwoMechanics($garage);

        $this->artisan('garage:check-timeouts')->assertExitCode(0);

        $events = ApprovalEvent::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('event_type', ApprovalEvent::EVENT_STAFF_NOTIFICATION_DISPATCHED)
            ->get();

        $this->assertGreaterThanOrEqual(2, $events->count());
        $mechanicIds = $events->pluck('payload.mechanic_id')->unique()->values()->all();
        $this->assertContains($mech1->id, $mechanicIds);
        $this->assertContains($mech2->id, $mechanicIds);
    }

    public function test_policy_working_hours_skips_when_outside_window(): void
    {
        $now = now();
        $dayKey = strtolower($now->format('D'));

        $beforeNow = $now->copy()->subHours(3)->format('H:i');
        $alsoBefore = $now->copy()->subHours(1)->format('H:i');

        $garage = $this->makeGarage(
            Garage::TIMEOUT_POLICY_WORKING_HOURS,
            workingHours: [$dayKey => ['open' => $beforeNow, 'close' => $alsoBefore]],
        );
        [$job] = $this->makeTimedOutJobWithTwoMechanics($garage);

        $this->artisan('garage:check-timeouts')->assertExitCode(0);

        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_STAFF_NOTIFICATION_DISPATCHED,
        ]);
    }

    public function test_policy_on_call_routes_to_current_on_call_mechanic(): void
    {
        $garage = $this->makeGarage(Garage::TIMEOUT_POLICY_ON_CALL);
        [$job, $mech1, $mech2] = $this->makeTimedOutJobWithTwoMechanics($garage);

        MechanicOnCall::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'mechanic_id' => $mech2->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $this->artisan('garage:check-timeouts')->assertExitCode(0);

        $events = ApprovalEvent::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('event_type', ApprovalEvent::EVENT_STAFF_NOTIFICATION_DISPATCHED)
            ->get();

        $mechanicIds = $events->pluck('payload.mechanic_id')->unique()->values()->all();
        $this->assertContains($mech2->id, $mechanicIds);
        $this->assertNotContains($mech1->id, $mechanicIds);
    }

    public function test_policy_on_call_falls_back_to_broadcast_when_nobody_on_call(): void
    {
        $garage = $this->makeGarage(Garage::TIMEOUT_POLICY_ON_CALL);
        [$job, $mech1, $mech2] = $this->makeTimedOutJobWithTwoMechanics($garage);

        $this->artisan('garage:check-timeouts')->assertExitCode(0);

        $events = ApprovalEvent::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('event_type', ApprovalEvent::EVENT_STAFF_NOTIFICATION_DISPATCHED)
            ->get();

        $mechanicIds = $events->pluck('payload.mechanic_id')->unique()->values()->all();
        $this->assertContains($mech1->id, $mechanicIds);
        $this->assertContains($mech2->id, $mechanicIds);
    }

    /**
     * @param  array<string, mixed>|null  $workingHours
     */
    private function makeGarage(string $policy, ?array $workingHours = null): Garage
    {
        return Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'staff_channel_toggle_default' => true,
            'timeout_reminder_policy' => $policy,
            'working_hours' => $workingHours,
            'locale' => 'en',
        ]);
    }

    /**
     * @return array{0: RepairJob, 1: Mechanic, 2: Mechanic}
     */
    private function makeTimedOutJobWithTwoMechanics(Garage $garage): array
    {
        $user1 = User::factory()->create();
        $user1->forceFill(['crm_user_id' => 'crm-user-mech-1-' . uniqid()])->save();

        $user2 = User::factory()->create();
        $user2->forceFill(['crm_user_id' => 'crm-user-mech-2-' . uniqid()])->save();

        $mech1 = Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user1->id,
            'role' => Mechanic::ROLE_MECHANIC,
            'is_active' => true,
        ]);

        $mech2 = Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user2->id,
            'role' => Mechanic::ROLE_MECHANIC,
            'is_active' => true,
        ]);

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-' . uniqid(),
            'registration' => Str::upper(Str::random(7)),
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        $job = RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_AWAITING_APPROVAL,
        ]);

        $job->mechanics()->attach([$mech1->id, $mech2->id]);

        JobStateTransition::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'from_state' => RepairJob::STATE_IN_PROGRESS,
            'to_state' => RepairJob::STATE_AWAITING_APPROVAL,
            'actor_id' => 'system',
            'occurred_at' => now()->subHours(48),
        ]);

        return [$job, $mech1, $mech2];
    }
}
