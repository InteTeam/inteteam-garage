<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\ApprovalEvent;
use App\Models\Estimate;
use App\Models\Garage;
use App\Models\LineItem;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MechanicResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*/api/v1/internal/notifications' => Http::response(['status' => 'ok'], 200)]);
    }

    public function test_mechanic_can_respond_to_customer_query(): void
    {
        [$garage, $user] = $this->makeGarageWithAdmin();
        [$job, $lineItem] = $this->makeJobWithLineItem($garage, RepairJob::STATE_CUSTOMER_QUERY);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.line-items.respond', ['job' => $job->id, 'lineItem' => $lineItem->id]), [
                'message' => 'The water pump is included in the timing belt kit price.',
            ])
            ->assertRedirect();

        $event = ApprovalEvent::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('event_type', ApprovalEvent::EVENT_MECHANIC_RESPONSE)
            ->firstOrFail();

        $this->assertSame(ApprovalEvent::ACTOR_MECHANIC, $event->actor_type);
        $this->assertSame($lineItem->id, $event->payload['line_item_id']);
        $this->assertSame('The water pump is included in the timing belt kit price.', $event->payload['message']);

        $this->assertSame(RepairJob::STATE_AWAITING_APPROVAL, $job->fresh()->state);
        Http::assertSentCount(1);
    }

    public function test_response_from_awaiting_approval_state_does_not_transition(): void
    {
        [$garage, $user] = $this->makeGarageWithAdmin();
        [$job, $lineItem] = $this->makeJobWithLineItem($garage, RepairJob::STATE_AWAITING_APPROVAL);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.line-items.respond', ['job' => $job->id, 'lineItem' => $lineItem->id]), [
                'message' => 'Just confirming the price.',
            ])
            ->assertRedirect();

        $this->assertSame(RepairJob::STATE_AWAITING_APPROVAL, $job->fresh()->state);
        $this->assertDatabaseHas('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_MECHANIC_RESPONSE,
        ]);
    }

    public function test_empty_message_is_rejected(): void
    {
        [$garage, $user] = $this->makeGarageWithAdmin();
        [$job, $lineItem] = $this->makeJobWithLineItem($garage, RepairJob::STATE_CUSTOMER_QUERY);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.line-items.respond', ['job' => $job->id, 'lineItem' => $lineItem->id]), [])
            ->assertSessionHasErrors('message');

        $this->assertSame(RepairJob::STATE_CUSTOMER_QUERY, $job->fresh()->state);
        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_MECHANIC_RESPONSE,
        ]);
        Http::assertNothingSent();
    }

    public function test_non_admin_mechanic_cannot_respond(): void
    {
        [$garage, $user] = $this->makeGarageWithMechanic(Mechanic::ROLE_MECHANIC);
        [$job, $lineItem] = $this->makeJobWithLineItem($garage, RepairJob::STATE_CUSTOMER_QUERY);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.line-items.respond', ['job' => $job->id, 'lineItem' => $lineItem->id]), [
                'message' => 'Anything',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_MECHANIC_RESPONSE,
        ]);
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

    /**
     * @return array{0: RepairJob, 1: LineItem}
     */
    private function makeJobWithLineItem(Garage $garage, string $state): array
    {
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
            'state' => $state,
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
            'description' => 'Timing belt kit',
            'price' => 480.00,
            'status' => LineItem::STATUS_PENDING,
        ]);

        return [$job, $lineItem];
    }
}
