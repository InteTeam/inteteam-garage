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

final class ScopeChangeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*/api/v1/internal/notifications' => Http::response(['status' => 'ok'], 200)]);
    }

    public function test_mechanic_can_raise_scope_change_from_approved_state(): void
    {
        [$garage, $user] = $this->makeGarageWithAdmin();
        $job = $this->makeJob($garage, RepairJob::STATE_APPROVED);
        $this->makeEstimate($job, revision: 1);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.scope-change.store', $job->id), [
                'line_items' => [
                    ['description' => 'Cracked timing belt cover', 'price' => 180.00],
                    ['description' => 'Replacement coolant hose', 'price' => 65.00],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(RepairJob::STATE_SCOPE_CHANGE, $job->fresh()->state);

        $newEstimate = Estimate::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->orderByDesc('revision_number')
            ->firstOrFail();

        $this->assertSame(2, $newEstimate->revision_number);
        $this->assertSame(2, $newEstimate->lineItems()->count());
        $this->assertTrue(
            $newEstimate->lineItems()->where('status', LineItem::STATUS_PENDING)->count() === 2,
        );

        $event = ApprovalEvent::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('event_type', ApprovalEvent::EVENT_SCOPE_CHANGE)
            ->firstOrFail();

        $this->assertSame(ApprovalEvent::ACTOR_MECHANIC, $event->actor_type);
        $this->assertSame(2, $event->payload['revision_number']);
        $this->assertSame(2, $event->payload['line_item_count']);

        Http::assertSentCount(1);
    }

    public function test_scope_change_rejected_from_non_approved_state(): void
    {
        [$garage, $user] = $this->makeGarageWithAdmin();
        $job = $this->makeJob($garage, RepairJob::STATE_IN_PROGRESS);
        $this->makeEstimate($job, revision: 1);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.scope-change.store', $job->id), [
                'line_items' => [
                    ['description' => 'Extra work', 'price' => 50.00],
                ],
            ])
            ->assertStatus(500);

        $this->assertSame(RepairJob::STATE_IN_PROGRESS, $job->fresh()->state);
        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_SCOPE_CHANGE,
        ]);
        Http::assertNothingSent();
    }

    public function test_scope_change_rejected_when_line_items_missing(): void
    {
        [$garage, $user] = $this->makeGarageWithAdmin();
        $job = $this->makeJob($garage, RepairJob::STATE_APPROVED);
        $this->makeEstimate($job, revision: 1);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.scope-change.store', $job->id), [])
            ->assertSessionHasErrors('line_items');

        $this->assertSame(RepairJob::STATE_APPROVED, $job->fresh()->state);
    }

    public function test_scope_change_rejected_when_line_items_empty_array(): void
    {
        [$garage, $user] = $this->makeGarageWithAdmin();
        $job = $this->makeJob($garage, RepairJob::STATE_APPROVED);
        $this->makeEstimate($job, revision: 1);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.scope-change.store', $job->id), ['line_items' => []])
            ->assertSessionHasErrors('line_items');

        $this->assertSame(RepairJob::STATE_APPROVED, $job->fresh()->state);
    }

    public function test_non_admin_mechanic_cannot_raise_scope_change(): void
    {
        [$garage, $user] = $this->makeGarageWithMechanic(Mechanic::ROLE_MECHANIC);
        $job = $this->makeJob($garage, RepairJob::STATE_APPROVED);
        $this->makeEstimate($job, revision: 1);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.scope-change.store', $job->id), [
                'line_items' => [['description' => 'Extra', 'price' => 10]],
            ])
            ->assertForbidden();

        $this->assertSame(RepairJob::STATE_APPROVED, $job->fresh()->state);
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

    private function makeJob(Garage $garage, string $state): RepairJob
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
            'state' => $state,
        ]);
    }

    private function makeEstimate(RepairJob $job, int $revision): Estimate
    {
        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'revision_number' => $revision,
            'sent_at' => now(),
        ]);

        LineItem::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'estimate_id' => $estimate->id,
            'description' => 'Original work',
            'price' => 200.00,
            'status' => LineItem::STATUS_APPROVED,
        ]);

        return $estimate;
    }
}
