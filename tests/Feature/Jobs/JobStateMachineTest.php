<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\Estimate;
use App\Models\Garage;
use App\Models\HandoverInspection;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ApprovalEventService;
use App\Services\JobStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class JobStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private JobStateMachine $stateMachine;

    private Garage $garage;

    private RepairJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateMachine = new JobStateMachine(new ApprovalEventService);

        $this->garage = Garage::create([
            'name' => 'Test Garage',
            'slug' => 'test-garage',
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);

        $user = User::factory()->create();
        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'crm_customer_id' => 'crm-123',
            'registration' => 'AB12 CDE',
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        $this->job = RepairJob::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_CREATED,
        ]);
    }

    public function test_valid_transition_created_to_booked(): void
    {
        $this->stateMachine->transition($this->job, RepairJob::STATE_BOOKED);

        $this->job->refresh();
        $this->assertEquals(RepairJob::STATE_BOOKED, $this->job->state);
        $this->assertDatabaseHas('job_state_transitions', [
            'job_id' => $this->job->id,
            'from_state' => RepairJob::STATE_CREATED,
            'to_state' => RepairJob::STATE_BOOKED,
        ]);
    }

    public function test_invalid_transition_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid state transition/');

        $this->stateMachine->transition($this->job, RepairJob::STATE_COLLECTED);
    }

    public function test_cannot_send_estimate_without_line_items(): void
    {
        $this->job->update(['state' => RepairJob::STATE_IN_PROGRESS]);

        Estimate::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'job_id' => $this->job->id,
            'revision_number' => 1,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no line items/');

        $this->stateMachine->transition($this->job, RepairJob::STATE_AWAITING_APPROVAL);
    }

    public function test_can_send_estimate_with_line_items(): void
    {
        $this->job->update(['state' => RepairJob::STATE_IN_PROGRESS]);

        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'job_id' => $this->job->id,
            'revision_number' => 1,
        ]);

        LineItem::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'estimate_id' => $estimate->id,
            'description' => 'Brake pads',
            'price' => 120.00,
            'status' => LineItem::STATUS_PENDING,
        ]);

        $this->stateMachine->transition($this->job, RepairJob::STATE_AWAITING_APPROVAL);

        $this->job->refresh();
        $this->assertEquals(RepairJob::STATE_AWAITING_APPROVAL, $this->job->state);
    }

    public function test_cannot_complete_with_pending_line_items(): void
    {
        $this->job->update(['state' => RepairJob::STATE_APPROVED]);

        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'job_id' => $this->job->id,
            'revision_number' => 1,
        ]);

        LineItem::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'estimate_id' => $estimate->id,
            'description' => 'Clutch',
            'price' => 450.00,
            'status' => LineItem::STATUS_PENDING,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/approved or declined/');

        $this->stateMachine->transition($this->job, RepairJob::STATE_COMPLETED);
    }

    public function test_cannot_collect_without_handover(): void
    {
        $this->job->update(['state' => RepairJob::STATE_AWAITING_COLLECTION]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/handover inspection/');

        $this->stateMachine->transition($this->job, RepairJob::STATE_COLLECTED);
    }

    public function test_cannot_collect_without_payment_when_enabled(): void
    {
        $this->garage->update(['online_payment_enabled' => true]);
        $this->job->update(['state' => RepairJob::STATE_AWAITING_COLLECTION]);

        HandoverInspection::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'job_id' => $this->job->id,
            'submitted_by_token' => 'test-token',
            'submitted_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/payment has not been confirmed/');

        $this->stateMachine->transition($this->job, RepairJob::STATE_COLLECTED);
    }

    public function test_can_collect_with_handover_and_payment(): void
    {
        $this->garage->update(['online_payment_enabled' => true]);
        $this->job->update([
            'state' => RepairJob::STATE_AWAITING_COLLECTION,
            'payment_confirmed_at' => now(),
        ]);

        HandoverInspection::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'job_id' => $this->job->id,
            'submitted_by_token' => 'test-token',
            'submitted_at' => now(),
        ]);

        $this->stateMachine->transition($this->job, RepairJob::STATE_COLLECTED);

        $this->job->refresh();
        $this->assertEquals(RepairJob::STATE_COLLECTED, $this->job->state);
    }

    public function test_can_collect_without_payment_when_disabled(): void
    {
        $this->job->update(['state' => RepairJob::STATE_AWAITING_COLLECTION]);

        HandoverInspection::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'job_id' => $this->job->id,
            'submitted_by_token' => 'test-token',
            'submitted_at' => now(),
        ]);

        $this->stateMachine->transition($this->job, RepairJob::STATE_COLLECTED);

        $this->job->refresh();
        $this->assertEquals(RepairJob::STATE_COLLECTED, $this->job->state);
    }
}
