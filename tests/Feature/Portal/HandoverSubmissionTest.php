<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\ApprovalEvent;
use App\Models\Estimate;
use App\Models\Garage;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Models\SignedPortalToken;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HandoverSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_with_all_items_accepted_succeeds(): void
    {
        [$job, $lineItem, $token] = $this->scenario();

        $this->post(
            route('portal.handover.submit', ['token' => $token->token]),
            ['items' => [
                ['line_item_id' => $lineItem->id, 'accepted' => true, 'notes' => null],
            ]],
        )->assertRedirect();

        $this->assertDatabaseHas('handover_inspections', [
            'job_id' => $job->id,
            'submitted_by_token' => $token->token,
        ]);
        $this->assertDatabaseHas('handover_items', [
            'line_item_id' => $lineItem->id,
            'accepted' => true,
            'notes' => null,
        ]);
        $this->assertDatabaseHas('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_HANDOVER_SUBMITTED,
            'actor_type' => ApprovalEvent::ACTOR_CUSTOMER,
        ]);
    }

    public function test_submission_rejected_when_declined_item_has_no_notes(): void
    {
        [$job, $lineItem, $token] = $this->scenario();

        $this->post(
            route('portal.handover.submit', ['token' => $token->token]),
            ['items' => [
                ['line_item_id' => $lineItem->id, 'accepted' => false, 'notes' => null],
            ]],
        )->assertSessionHasErrors('items');

        $this->assertDatabaseMissing('handover_inspections', ['job_id' => $job->id]);
        $this->assertDatabaseMissing('handover_items', ['line_item_id' => $lineItem->id]);
        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_HANDOVER_SUBMITTED,
        ]);
    }

    public function test_submission_with_declined_item_and_notes_succeeds(): void
    {
        [$job, $lineItem, $token] = $this->scenario();

        $this->post(
            route('portal.handover.submit', ['token' => $token->token]),
            ['items' => [
                [
                    'line_item_id' => $lineItem->id,
                    'accepted' => false,
                    'notes' => 'Brake pads are squealing on cold starts.',
                ],
            ]],
        )->assertRedirect();

        $this->assertDatabaseHas('handover_items', [
            'line_item_id' => $lineItem->id,
            'accepted' => false,
            'notes' => 'Brake pads are squealing on cold starts.',
        ]);
    }

    public function test_submission_rejected_when_items_array_missing(): void
    {
        [, , $token] = $this->scenario();

        $this->post(route('portal.handover.submit', ['token' => $token->token]), [])
            ->assertSessionHasErrors('items');
    }

    public function test_submission_rejected_when_items_array_empty(): void
    {
        [, , $token] = $this->scenario();

        $this->post(
            route('portal.handover.submit', ['token' => $token->token]),
            ['items' => []],
        )->assertSessionHasErrors('items');
    }

    /**
     * @return array{0: RepairJob, 1: LineItem, 2: SignedPortalToken}
     */
    private function scenario(): array
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

        return [$job, $lineItem, $token];
    }
}
