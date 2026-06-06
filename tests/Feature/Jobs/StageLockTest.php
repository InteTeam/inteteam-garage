<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\Estimate;
use App\Models\Garage;
use App\Models\JobStage;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Models\Vehicle;
use App\Services\ApprovalEventService;
use App\Services\JobStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageLockTest extends TestCase
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

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'crm_customer_id' => 'crm-sl-001',
            'registration' => 'SL12 OCK',
            'make' => 'Audi',
            'model' => 'A4',
        ]);

        $this->job = RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $this->garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_IN_PROGRESS,
        ]);

        $this->seedAllStages();
    }

    public function test_diagnosis_stages_lock_on_transition_to_awaiting_approval(): void
    {
        $this->seedEstimateWithLineItem();

        $this->stateMachine->transition($this->job, RepairJob::STATE_AWAITING_APPROVAL);

        $this->assertStageLocked(JobStage::STAGE_PRE_INSPECTION);
        $this->assertStageLocked(JobStage::STAGE_DISASSEMBLY);
        $this->assertStageLocked(JobStage::STAGE_FAULT_FOUND);
        $this->assertStageUnlocked(JobStage::STAGE_REPAIR);
        $this->assertStageUnlocked(JobStage::STAGE_COMPLETE);
    }

    public function test_repair_stage_unlocked_during_approved_then_locks_on_completed(): void
    {
        $this->seedEstimateWithLineItem();
        $this->stateMachine->transition($this->job, RepairJob::STATE_AWAITING_APPROVAL);
        $this->resolveAllLineItems();
        $this->stateMachine->transition($this->job, RepairJob::STATE_APPROVED);

        $this->assertStageUnlocked(JobStage::STAGE_REPAIR);

        $this->stateMachine->transition($this->job, RepairJob::STATE_COMPLETED);

        $this->assertStageLocked(JobStage::STAGE_REPAIR);
        $this->assertStageUnlocked(JobStage::STAGE_COMPLETE);
    }

    public function test_lock_is_idempotent_across_repeat_transitions(): void
    {
        $this->seedEstimateWithLineItem();
        $this->stateMachine->transition($this->job, RepairJob::STATE_AWAITING_APPROVAL);

        $originalLockedAt = $this->stageByName(JobStage::STAGE_DISASSEMBLY)->locked_at;
        $this->assertNotNull($originalLockedAt);

        $this->stateMachine->transition($this->job, RepairJob::STATE_CUSTOMER_QUERY);
        $this->stateMachine->transition($this->job, RepairJob::STATE_AWAITING_APPROVAL);

        $this->assertEquals(
            $originalLockedAt->toDateTimeString(),
            $this->stageByName(JobStage::STAGE_DISASSEMBLY)->locked_at->toDateTimeString(),
        );
    }

    private function seedAllStages(): void
    {
        foreach (JobStage::STAGES as $sortOrder => $name) {
            JobStage::withoutGlobalScopes()->create([
                'garage_id' => $this->garage->id,
                'job_id' => $this->job->id,
                'name' => $name,
                'sort_order' => $sortOrder + 1,
            ]);
        }
    }

    private function seedEstimateWithLineItem(): void
    {
        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'job_id' => $this->job->id,
            'revision_number' => 1,
        ]);

        LineItem::withoutGlobalScopes()->create([
            'garage_id' => $this->garage->id,
            'estimate_id' => $estimate->id,
            'description' => 'Timing belt',
            'price' => 300.00,
            'status' => LineItem::STATUS_PENDING,
        ]);
    }

    private function resolveAllLineItems(): void
    {
        LineItem::withoutGlobalScopes()
            ->whereIn('estimate_id', $this->job->estimates()->pluck('id'))
            ->update(['status' => LineItem::STATUS_APPROVED]);
    }

    private function stageByName(string $name): JobStage
    {
        return JobStage::withoutGlobalScopes()
            ->where('job_id', $this->job->id)
            ->where('name', $name)
            ->firstOrFail();
    }

    private function assertStageLocked(string $name): void
    {
        $this->assertNotNull(
            $this->stageByName($name)->locked_at,
            "Stage [{$name}] expected to be locked but is not.",
        );
    }

    private function assertStageUnlocked(string $name): void
    {
        $this->assertNull(
            $this->stageByName($name)->locked_at,
            "Stage [{$name}] expected to be unlocked but is locked.",
        );
    }
}
