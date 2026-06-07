<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\ApprovalEvent;
use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\SignedPortalToken;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NotificationPreferenceAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creation_seeds_preference_from_garage_default_without_event(): void
    {
        $garage = $this->makeGarage(Garage::CHANNEL_SMS);
        $job = $this->makeJob($garage);

        $this->assertDatabaseHas('notification_preferences', [
            'job_id' => $job->id,
            'garage_id' => $garage->id,
            'channel' => Garage::CHANNEL_SMS,
            'set_by' => 'admin',
        ]);

        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_PREFERENCE_CHANGED,
        ]);
    }

    public function test_admin_override_endpoint_records_preference_changed_event(): void
    {
        [$garage, $user] = $this->makeGarageWithAdmin();
        $job = $this->makeJob($garage);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->put(route('jobs.notification-preference.update', $job->id), [
                'channel' => Garage::CHANNEL_SMS,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_preferences', [
            'job_id' => $job->id,
            'channel' => Garage::CHANNEL_SMS,
            'set_by' => 'admin',
        ]);

        $event = ApprovalEvent::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('event_type', ApprovalEvent::EVENT_PREFERENCE_CHANGED)
            ->firstOrFail();

        $this->assertSame(ApprovalEvent::ACTOR_MECHANIC, $event->actor_type);
        $this->assertSame((string) $user->id, $event->actor_id);
        $this->assertSame(Garage::CHANNEL_EMAIL, $event->payload['from']);
        $this->assertSame(Garage::CHANNEL_SMS, $event->payload['to']);
    }

    public function test_admin_override_with_same_channel_does_not_log_event(): void
    {
        [$garage, $user] = $this->makeGarageWithAdmin();
        $job = $this->makeJob($garage);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->put(route('jobs.notification-preference.update', $job->id), [
                'channel' => Garage::CHANNEL_EMAIL,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_PREFERENCE_CHANGED,
        ]);
    }

    public function test_non_admin_mechanic_cannot_override_notification_preference(): void
    {
        [$garage, $user] = $this->makeGarageWithMechanic(Mechanic::ROLE_MECHANIC);
        $job = $this->makeJob($garage);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->put(route('jobs.notification-preference.update', $job->id), [
                'channel' => Garage::CHANNEL_SMS,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('notification_preferences', [
            'job_id' => $job->id,
            'channel' => Garage::CHANNEL_EMAIL,
        ]);
    }

    public function test_customer_portal_update_records_preference_changed_event(): void
    {
        $garage = $this->makeGarage(Garage::CHANNEL_EMAIL);
        $job = $this->makeJob($garage);
        $token = $this->makeToken($job);

        $this->post(route('portal.preference.update', $token->token), [
            'channel' => Garage::CHANNEL_IN_APP,
        ])->assertRedirect();

        $this->assertDatabaseHas('notification_preferences', [
            'job_id' => $job->id,
            'channel' => Garage::CHANNEL_IN_APP,
            'set_by' => 'customer',
        ]);

        $event = ApprovalEvent::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('event_type', ApprovalEvent::EVENT_PREFERENCE_CHANGED)
            ->firstOrFail();

        $this->assertSame(ApprovalEvent::ACTOR_CUSTOMER, $event->actor_type);
        $this->assertSame(Garage::CHANNEL_EMAIL, $event->payload['from']);
        $this->assertSame(Garage::CHANNEL_IN_APP, $event->payload['to']);
    }

    private function makeGarage(string $defaultChannel = Garage::CHANNEL_EMAIL): Garage
    {
        return Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => $defaultChannel,
            'locale' => 'en',
        ]);
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

    private function makeJob(Garage $garage): RepairJob
    {
        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-' . uniqid(),
            'registration' => Str::upper(Str::random(7)),
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        /** @var RepairJob $job */
        $job = RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_CREATED,
        ]);

        return $job;
    }

    private function makeToken(RepairJob $job): SignedPortalToken
    {
        return SignedPortalToken::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'token' => Str::ulid()->toString(),
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
        ]);
    }
}
