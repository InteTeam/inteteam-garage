<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\ApprovalEvent;
use App\Models\Estimate;
use App\Models\Garage;
use App\Models\LineItem;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\SignedPortalToken;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HandoverStaffNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    public function test_declined_item_triggers_staff_notification_per_assigned_mechanic(): void
    {
        [$job, $lineItem, $token, $mechanic] = $this->scenario(withCrmUserId: 'crm-user-mech-1');

        $this->post(
            route('portal.handover.submit', ['token' => $token->token]),
            ['items' => [[
                'line_item_id' => $lineItem->id,
                'accepted' => false,
                'notes' => 'Brake pads squealing on cold starts.',
            ]]],
        )->assertRedirect();

        $event = ApprovalEvent::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('event_type', ApprovalEvent::EVENT_STAFF_NOTIFICATION_DISPATCHED)
            ->firstOrFail();

        $this->assertSame($mechanic->id, $event->payload['mechanic_id']);
        $this->assertSame('handover_flagged', $event->payload['trigger']);
    }

    public function test_accepted_item_with_notes_also_triggers_staff_notification(): void
    {
        [$job, $lineItem, $token, $mechanic] = $this->scenario(withCrmUserId: 'crm-user-mech-2');

        $this->post(
            route('portal.handover.submit', ['token' => $token->token]),
            ['items' => [[
                'line_item_id' => $lineItem->id,
                'accepted' => true,
                'notes' => 'Looks good, just a minor cosmetic mark on the caliper.',
            ]]],
        )->assertRedirect();

        $this->assertDatabaseHas('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_STAFF_NOTIFICATION_DISPATCHED,
        ]);
    }

    public function test_all_clean_submission_does_not_trigger_staff_notification(): void
    {
        [$job, $lineItem, $token] = $this->scenario(withCrmUserId: 'crm-user-mech-3');

        $this->post(
            route('portal.handover.submit', ['token' => $token->token]),
            ['items' => [[
                'line_item_id' => $lineItem->id,
                'accepted' => true,
                'notes' => null,
            ]]],
        )->assertRedirect();

        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_STAFF_NOTIFICATION_DISPATCHED,
        ]);
    }

    public function test_mechanic_without_crm_user_id_does_not_write_audit_row(): void
    {
        [$job, $lineItem, $token] = $this->scenario(withCrmUserId: null);

        $this->post(
            route('portal.handover.submit', ['token' => $token->token]),
            ['items' => [[
                'line_item_id' => $lineItem->id,
                'accepted' => false,
                'notes' => 'Item is wrong.',
            ]]],
        )->assertRedirect();

        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_STAFF_NOTIFICATION_DISPATCHED,
        ]);
    }

    /**
     * @return array{0: RepairJob, 1: LineItem, 2: SignedPortalToken, 3: Mechanic}
     */
    private function scenario(?string $withCrmUserId): array
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
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

        $job = RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_AWAITING_COLLECTION,
        ]);

        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'revision_number' => 1,
            'sent_at' => now(),
        ]);

        $lineItem = LineItem::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'estimate_id' => $estimate->id,
            'description' => 'Brake pads',
            'price' => 120.00,
            'status' => LineItem::STATUS_APPROVED,
        ]);

        $token = SignedPortalToken::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'token' => Str::ulid()->toString(),
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
        ]);

        $user = User::factory()->create();
        if ($withCrmUserId !== null) {
            $user->forceFill(['crm_user_id' => $withCrmUserId])->save();
        }

        $mechanic = Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_MECHANIC,
            'is_active' => true,
        ]);

        $job->mechanics()->attach($mechanic->id);

        return [$job, $lineItem, $token, $mechanic];
    }
}
