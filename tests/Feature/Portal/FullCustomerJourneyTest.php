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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FullCustomerJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.garage.internal_secret', self::WEBHOOK_SECRET);
        Http::fake([
            '*/api/v1/internal/notifications' => Http::response(['status' => 'ok'], 200),
            '*/api/v1/internal/customers/*' => Http::response(['data' => ['locale' => 'en']], 200),
        ]);
    }

    public function test_customer_journey_from_estimate_sent_to_collected_state(): void
    {
        [$garage, $admin, $job, $estimate, $brakes, $tyres, $token] = $this->scenario();

        $this->get(route('portal.show', ['token' => $token->token]))->assertOk();

        $this->post(route('portal.line-items.approve', [
            'token' => $token->token,
            'lineItem' => $brakes->id,
        ]))->assertRedirect();
        $this->post(route('portal.line-items.approve', [
            'token' => $token->token,
            'lineItem' => $tyres->id,
        ]))->assertRedirect();

        $this->assertDatabaseHas('line_items', ['id' => $brakes->id, 'status' => LineItem::STATUS_APPROVED]);
        $this->assertDatabaseHas('line_items', ['id' => $tyres->id, 'status' => LineItem::STATUS_APPROVED]);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.transition', $job->id), ['state' => RepairJob::STATE_APPROVED])
            ->assertRedirect();
        $this->post(route('jobs.transition', $job->id), ['state' => RepairJob::STATE_COMPLETED])
            ->assertRedirect();
        $this->post(route('jobs.transition', $job->id), ['state' => RepairJob::STATE_AWAITING_COLLECTION])
            ->assertRedirect();

        $this->assertSame(RepairJob::STATE_AWAITING_COLLECTION, $job->fresh()->state);

        $this->post(
            route('portal.handover.submit', ['token' => $token->token]),
            ['items' => [
                ['line_item_id' => $brakes->id, 'accepted' => true, 'notes' => null],
                ['line_item_id' => $tyres->id, 'accepted' => true, 'notes' => null],
            ]],
        )->assertRedirect();

        $this->assertDatabaseHas('handover_inspections', [
            'job_id' => $job->id,
            'submitted_by_token' => $token->token,
        ]);

        $this->withHeader('X-Internal-Secret', self::WEBHOOK_SECRET)
            ->postJson('/webhooks/payment-confirmed', [
                'job_id' => $job->id,
                'payment_reference' => 'pay-journey-' . uniqid(),
            ])->assertNoContent();

        $this->assertNotNull($job->fresh()->payment_confirmed_at);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('jobs.transition', $job->id), ['state' => RepairJob::STATE_COLLECTED])
            ->assertRedirect();

        $this->assertSame(RepairJob::STATE_COLLECTED, $job->fresh()->state);

        foreach ([
            ApprovalEvent::EVENT_LINE_ITEM_APPROVED,
            ApprovalEvent::EVENT_HANDOVER_SUBMITTED,
            ApprovalEvent::EVENT_PAYMENT_CONFIRMED,
        ] as $eventType) {
            $this->assertDatabaseHas('approval_events', [
                'job_id' => $job->id,
                'event_type' => $eventType,
            ]);
        }

        $this->assertCount(2, ApprovalEvent::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('event_type', ApprovalEvent::EVENT_LINE_ITEM_APPROVED)
            ->get());
    }

    /**
     * @return array{0: Garage, 1: User, 2: RepairJob, 3: Estimate, 4: LineItem, 5: LineItem, 6: SignedPortalToken}
     */
    private function scenario(): array
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => true,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);

        $admin = User::factory()->create();
        Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $admin->id,
            'role' => Mechanic::ROLE_GARAGE_ADMIN,
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

        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'revision_number' => 1,
            'sent_at' => now(),
        ]);

        $brakes = LineItem::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'estimate_id' => $estimate->id,
            'description' => 'Brake pads',
            'price' => 120.00,
            'status' => LineItem::STATUS_PENDING,
        ]);

        $tyres = LineItem::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'estimate_id' => $estimate->id,
            'description' => 'Front tyres',
            'price' => 200.00,
            'status' => LineItem::STATUS_PENDING,
        ]);

        $token = SignedPortalToken::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'token' => Str::ulid()->toString(),
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
        ]);

        return [$garage, $admin, $job, $estimate, $brakes, $tyres, $token];
    }
}
