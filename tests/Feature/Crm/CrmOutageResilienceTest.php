<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Models\ApprovalEvent;
use App\Models\Estimate;
use App\Models\Garage;
use App\Models\LineItem;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobStateMachine;
use App\Services\SignedPortalTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The CRM is a separate service behind a reverse proxy, and two of the endpoints
 * this app calls are not deployed there yet. A notification failure must never
 * turn a *successful* domain write into a 500 for the mechanic — which is exactly
 * what happened: `CrmApiService` threw on any non-2xx and no caller caught it, so
 * sending an estimate committed the state transition and the audit row and *then*
 * blew up. The mechanic's retry then hit the "already sent" guard.
 */
final class CrmOutageResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.crm.url', 'https://crm.test');
        Config::set('services.crm.internal_url', 'https://crm.test');

        // Every CRM call fails — the posture of a real outage, and the current
        // posture of the un-deployed notification endpoint (404 from Laravel).
        Http::fake(['https://crm.test/*' => Http::response(['error' => 'Not Found'], 404)]);
    }

    public function test_sending_an_estimate_succeeds_while_crm_is_down(): void
    {
        [$garage, $admin, $job] = $this->makeJobWithEstimate();

        // `awaiting_approval` is only reachable from `in_progress`.
        $this->transitionTo($job, [RepairJob::STATE_BOOKED, RepairJob::STATE_IN_PROGRESS]);

        $estimate = $job->currentEstimate;

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post("/jobs/{$job->id}/estimates/{$estimate->id}/send")
            ->assertSessionHasNoErrors();

        $this->assertNotNull($estimate->fresh()->sent_at);
        $this->assertSame(RepairJob::STATE_AWAITING_APPROVAL, $job->fresh()->state);
        $this->assertDatabaseHas('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_ESTIMATE_SENT,
        ]);
    }

    public function test_raising_a_scope_change_succeeds_while_crm_is_down(): void
    {
        [$garage, $admin, $job] = $this->makeJobWithEstimate();

        $job->currentEstimate->lineItems()->update(['status' => LineItem::STATUS_APPROVED]);
        $this->transitionTo($job, [
            RepairJob::STATE_BOOKED,
            RepairJob::STATE_IN_PROGRESS,
            RepairJob::STATE_AWAITING_APPROVAL,
            RepairJob::STATE_APPROVED,
        ]);

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->post("/jobs/{$job->id}/scope-change", [
                'line_items' => [
                    ['description' => 'Extra strut', 'price' => 120.50],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(RepairJob::STATE_SCOPE_CHANGE, $job->fresh()->state);
        $this->assertDatabaseHas('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_SCOPE_CHANGE,
        ]);
    }

    public function test_portal_payment_request_flashes_an_error_instead_of_500(): void
    {
        [$garage, , $job] = $this->makeJobWithEstimate();
        $garage->forceFill(['online_payment_enabled' => true])->save();

        $job->currentEstimate->lineItems()->update(['status' => LineItem::STATUS_APPROVED]);

        $token = app(SignedPortalTokenService::class)->createForJob($job);

        $this->post("/portal/{$token->token}/payment/request")
            ->assertSessionHasErrors('payment');

        $this->assertNull($job->fresh()->payment_reference);
    }

    /**
     * @param  list<string>  $states
     */
    private function transitionTo(RepairJob $job, array $states): void
    {
        $machine = app(JobStateMachine::class);

        foreach ($states as $state) {
            $machine->transition($job, $state);
        }
    }

    /**
     * @return array{0: Garage, 1: User, 2: RepairJob}
     */
    private function makeJobWithEstimate(): array
    {
        $garage = Garage::create([
            'name' => 'Outage Garage',
            'slug' => 'outage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => Garage::CHANNEL_EMAIL,
            'locale' => 'en',
        ]);

        $user = User::factory()->create();

        Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_GARAGE_ADMIN,
            'is_active' => true,
        ]);

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-outage-1',
            'registration' => 'OU71 AGE',
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        $job = RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_CREATED,
        ]);

        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'revision_number' => 1,
        ]);

        LineItem::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'estimate_id' => $estimate->id,
            'description' => 'Front brake pads',
            'price' => 210.00,
            'status' => LineItem::STATUS_PENDING,
        ]);

        return [$garage, $user, $job->fresh()];
    }
}
