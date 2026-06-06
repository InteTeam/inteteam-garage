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
use App\Services\JobStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_flow_from_estimate_sent_to_approved_state(): void
    {
        $garage = $this->makeGarage();
        $job = $this->makeJob($garage, RepairJob::STATE_AWAITING_APPROVAL);
        $estimate = $this->makeEstimate($job, sent: true);

        $brakes = $this->makeLineItem($estimate, 'Brake pads', 120.00);
        $tyres = $this->makeLineItem($estimate, 'Front tyres', 200.00);

        $token = $this->makeToken($job);

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

        $approvalEvents = ApprovalEvent::query()
            ->where('job_id', $job->id)
            ->where('event_type', ApprovalEvent::EVENT_LINE_ITEM_APPROVED)
            ->get();
        $this->assertCount(2, $approvalEvents);
        foreach ($approvalEvents as $event) {
            $this->assertSame(ApprovalEvent::ACTOR_CUSTOMER, $event->actor_type);
            $this->assertArrayHasKey('line_item_id', $event->payload);
        }

        (new JobStateMachine)->transition($job->fresh(), RepairJob::STATE_APPROVED);

        $this->assertSame(RepairJob::STATE_APPROVED, $job->fresh()->state);
        $this->assertDatabaseHas('job_state_transitions', [
            'job_id' => $job->id,
            'from_state' => RepairJob::STATE_AWAITING_APPROVAL,
            'to_state' => RepairJob::STATE_APPROVED,
        ]);
    }

    public function test_decline_with_notes_logs_event_and_marks_line_item(): void
    {
        $garage = $this->makeGarage();
        $job = $this->makeJob($garage, RepairJob::STATE_AWAITING_APPROVAL);
        $estimate = $this->makeEstimate($job, sent: true);
        $lineItem = $this->makeLineItem($estimate, 'Optional wax polish', 60.00);

        $token = $this->makeToken($job);

        $this->post(
            route('portal.line-items.decline', ['token' => $token->token, 'lineItem' => $lineItem->id]),
            ['notes' => 'Too expensive for me right now'],
        )->assertRedirect();

        $this->assertDatabaseHas('line_items', [
            'id' => $lineItem->id,
            'status' => LineItem::STATUS_DECLINED,
            'customer_notes' => 'Too expensive for me right now',
        ]);
        $this->assertDatabaseHas('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_LINE_ITEM_DECLINED,
            'actor_type' => ApprovalEvent::ACTOR_CUSTOMER,
        ]);
    }

    public function test_decline_without_notes_is_rejected(): void
    {
        $garage = $this->makeGarage();
        $job = $this->makeJob($garage, RepairJob::STATE_AWAITING_APPROVAL);
        $estimate = $this->makeEstimate($job, sent: true);
        $lineItem = $this->makeLineItem($estimate, 'Cabin filter', 35.00);

        $token = $this->makeToken($job);

        $this->post(
            route('portal.line-items.decline', ['token' => $token->token, 'lineItem' => $lineItem->id]),
            [],
        )->assertSessionHasErrors('notes');

        $this->assertDatabaseHas('line_items', [
            'id' => $lineItem->id,
            'status' => LineItem::STATUS_PENDING,
        ]);
    }

    public function test_state_advance_to_approved_blocked_while_any_line_item_pending(): void
    {
        $garage = $this->makeGarage();
        $job = $this->makeJob($garage, RepairJob::STATE_AWAITING_APPROVAL);
        $estimate = $this->makeEstimate($job, sent: true);

        $resolved = $this->makeLineItem($estimate, 'Battery', 150.00);
        $pending = $this->makeLineItem($estimate, 'Wipers', 25.00);

        $token = $this->makeToken($job);

        $this->post(route('portal.line-items.approve', [
            'token' => $token->token,
            'lineItem' => $resolved->id,
        ]))->assertRedirect();

        $machine = new JobStateMachine;
        $machine->transition($job->fresh(), RepairJob::STATE_APPROVED);

        $this->assertSame(RepairJob::STATE_APPROVED, $job->fresh()->state);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/all line items must be approved or declined/');

        $machine->transition($job->fresh(), RepairJob::STATE_COMPLETED);

        $this->assertSame(LineItem::STATUS_PENDING, $pending->fresh()->status);
    }

    public function test_question_event_is_logged_without_changing_line_item_status(): void
    {
        $garage = $this->makeGarage();
        $job = $this->makeJob($garage, RepairJob::STATE_AWAITING_APPROVAL);
        $estimate = $this->makeEstimate($job, sent: true);
        $lineItem = $this->makeLineItem($estimate, 'Timing belt kit', 480.00);

        $token = $this->makeToken($job);

        $this->post(
            route('portal.line-items.question', ['token' => $token->token, 'lineItem' => $lineItem->id]),
            ['message' => 'Is the water pump included?'],
        )->assertRedirect();

        $this->assertDatabaseHas('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_CUSTOMER_QUESTION,
            'actor_type' => ApprovalEvent::ACTOR_CUSTOMER,
        ]);
        $this->assertDatabaseHas('line_items', [
            'id' => $lineItem->id,
            'status' => LineItem::STATUS_PENDING,
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

    private function makeEstimate(RepairJob $job, bool $sent = false): Estimate
    {
        return Estimate::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'revision_number' => 1,
            'sent_at' => $sent ? now() : null,
        ]);
    }

    private function makeLineItem(Estimate $estimate, string $description, float $price): LineItem
    {
        return LineItem::withoutGlobalScopes()->create([
            'garage_id' => $estimate->garage_id,
            'estimate_id' => $estimate->id,
            'description' => $description,
            'price' => $price,
            'status' => LineItem::STATUS_PENDING,
        ]);
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
