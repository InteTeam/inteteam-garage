<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Models\ApprovalEvent;
use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.garage.internal_secret', self::SECRET);
        Http::fake([
            '*/api/v1/internal/notifications' => Http::response(['status' => 'ok'], 200),
        ]);
    }

    public function test_missing_secret_header_rejects_with_401(): void
    {
        $job = $this->makeJob();

        $this->postJson('/webhooks/payment-confirmed', [
            'job_id' => $job->id,
            'payment_reference' => 'pay-123',
        ])->assertStatus(401);

        $this->assertNull($job->fresh()->payment_confirmed_at);
    }

    public function test_wrong_secret_header_rejects_with_401(): void
    {
        $job = $this->makeJob();

        $this->withHeader('X-Internal-Secret', 'wrong-secret')
            ->postJson('/webhooks/payment-confirmed', [
                'job_id' => $job->id,
                'payment_reference' => 'pay-123',
            ])->assertStatus(401);

        $this->assertNull($job->fresh()->payment_confirmed_at);
    }

    public function test_valid_payload_confirms_payment_and_dispatches_staff_notification(): void
    {
        [$job, $mechanic] = $this->makeJobWithAssignedMechanicHavingCrmUserId();

        $this->withHeader('X-Internal-Secret', self::SECRET)
            ->postJson('/webhooks/payment-confirmed', [
                'job_id' => $job->id,
                'payment_reference' => 'pay-confirmed-001',
            ])->assertNoContent();

        $this->assertNotNull($job->fresh()->payment_confirmed_at);

        $this->assertDatabaseHas('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_PAYMENT_CONFIRMED,
        ]);

        $this->assertDatabaseHas('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_STAFF_NOTIFICATION_DISPATCHED,
        ]);

        $staffEvent = ApprovalEvent::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('event_type', ApprovalEvent::EVENT_STAFF_NOTIFICATION_DISPATCHED)
            ->first();
        $this->assertSame($mechanic->id, $staffEvent->payload['mechanic_id']);
        $this->assertSame('payment_confirmed', $staffEvent->payload['trigger']);
    }

    public function test_valid_payload_without_crm_user_id_skips_staff_audit_row(): void
    {
        [$job] = $this->makeJobWithAssignedMechanicWithoutCrmUserId();

        $this->withHeader('X-Internal-Secret', self::SECRET)
            ->postJson('/webhooks/payment-confirmed', [
                'job_id' => $job->id,
                'payment_reference' => 'pay-confirmed-002',
            ])->assertNoContent();

        $this->assertDatabaseHas('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_PAYMENT_CONFIRMED,
        ]);

        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_STAFF_NOTIFICATION_DISPATCHED,
        ]);
    }

    public function test_invalid_payload_is_rejected(): void
    {
        $this->withHeader('X-Internal-Secret', self::SECRET)
            ->postJson('/webhooks/payment-confirmed', [])
            ->assertStatus(422);
    }

    private function makeJob(): RepairJob
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => true,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);

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
            'state' => RepairJob::STATE_AWAITING_COLLECTION,
        ]);
    }

    /**
     * @return array{0: RepairJob, 1: Mechanic}
     */
    private function makeJobWithAssignedMechanicHavingCrmUserId(): array
    {
        return $this->makeJobWithAssignedMechanic('crm-user-abc');
    }

    /**
     * @return array{0: RepairJob, 1: Mechanic}
     */
    private function makeJobWithAssignedMechanicWithoutCrmUserId(): array
    {
        return $this->makeJobWithAssignedMechanic(null);
    }

    /**
     * @return array{0: RepairJob, 1: Mechanic}
     */
    private function makeJobWithAssignedMechanic(?string $crmUserId): array
    {
        $job = $this->makeJob();

        $user = User::factory()->create();
        if ($crmUserId !== null) {
            $user->forceFill(['crm_user_id' => $crmUserId])->save();
        }

        $mechanic = Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_MECHANIC,
            'is_active' => true,
        ]);

        $job->mechanics()->attach($mechanic->id);

        return [$job, $mechanic];
    }
}
