<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\JobStage;
use App\Models\JobStateTransition;
use App\Models\RepairJob;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class JobStateMachine
{
    private const TRANSITIONS = [
        RepairJob::STATE_CREATED => [RepairJob::STATE_BOOKED],
        RepairJob::STATE_BOOKED => [RepairJob::STATE_IN_PROGRESS],
        RepairJob::STATE_IN_PROGRESS => [RepairJob::STATE_AWAITING_APPROVAL],
        RepairJob::STATE_AWAITING_APPROVAL => [RepairJob::STATE_CUSTOMER_QUERY, RepairJob::STATE_APPROVED],
        RepairJob::STATE_CUSTOMER_QUERY => [RepairJob::STATE_AWAITING_APPROVAL],
        RepairJob::STATE_SCOPE_CHANGE => [RepairJob::STATE_AWAITING_APPROVAL, RepairJob::STATE_IN_PROGRESS],
        RepairJob::STATE_APPROVED => [RepairJob::STATE_COMPLETED, RepairJob::STATE_SCOPE_CHANGE],
        RepairJob::STATE_COMPLETED => [RepairJob::STATE_AWAITING_COLLECTION],
        RepairJob::STATE_AWAITING_COLLECTION => [RepairJob::STATE_COLLECTED],
    ];

    private const STATE_ORDER = [
        RepairJob::STATE_CREATED => 0,
        RepairJob::STATE_BOOKED => 1,
        RepairJob::STATE_IN_PROGRESS => 2,
        RepairJob::STATE_AWAITING_APPROVAL => 3,
        RepairJob::STATE_CUSTOMER_QUERY => 3,
        RepairJob::STATE_SCOPE_CHANGE => 3,
        RepairJob::STATE_APPROVED => 4,
        RepairJob::STATE_COMPLETED => 5,
        RepairJob::STATE_AWAITING_COLLECTION => 6,
        RepairJob::STATE_COLLECTED => 7,
    ];

    private const STAGE_FINAL_ACTIVE_STATE = [
        JobStage::STAGE_PRE_INSPECTION => RepairJob::STATE_IN_PROGRESS,
        JobStage::STAGE_DISASSEMBLY => RepairJob::STATE_IN_PROGRESS,
        JobStage::STAGE_FAULT_FOUND => RepairJob::STATE_IN_PROGRESS,
        JobStage::STAGE_REPAIR => RepairJob::STATE_APPROVED,
        JobStage::STAGE_COMPLETE => RepairJob::STATE_COMPLETED,
    ];

    public function __construct() {}

    public function transition(RepairJob $job, string $toState, ?string $actorId = null): void
    {
        $fromState = $job->state;

        $this->assertAllowed($fromState, $toState);
        $this->runGuard($job, $toState);

        $job->state = $toState;
        $job->save();

        JobStateTransition::create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'from_state' => $fromState,
            'to_state' => $toState,
            'transitioned_by' => $actorId ?? Auth::id(),
            'occurred_at' => now(),
        ]);

        $this->lockStagesPastActivity($job, $toState);
    }

    private function lockStagesPastActivity(RepairJob $job, string $toState): void
    {
        $toStateOrder = self::STATE_ORDER[$toState] ?? null;

        if ($toStateOrder === null) {
            return;
        }

        $stages = $job->stages()->whereNull('locked_at')->get();

        foreach ($stages as $stage) {
            $finalActiveState = self::STAGE_FINAL_ACTIVE_STATE[$stage->name] ?? null;

            if ($finalActiveState === null) {
                continue;
            }

            if ($toStateOrder <= self::STATE_ORDER[$finalActiveState]) {
                continue;
            }

            $stage->forceFill(['locked_at' => now()])->save();
        }
    }

    public function canTransition(RepairJob $job, string $toState): bool
    {
        $allowed = self::TRANSITIONS[$job->state] ?? [];

        return in_array($toState, $allowed, true);
    }

    private function assertAllowed(string $fromState, string $toState): void
    {
        $allowed = self::TRANSITIONS[$fromState] ?? [];

        if (! in_array($toState, $allowed, true)) {
            throw new RuntimeException(
                "Invalid state transition from [{$fromState}] to [{$toState}]."
            );
        }
    }

    private function runGuard(RepairJob $job, string $toState): void
    {
        match ($toState) {
            RepairJob::STATE_AWAITING_APPROVAL => $this->guardAwaitingApproval($job),
            RepairJob::STATE_COMPLETED => $this->guardCompleted($job),
            RepairJob::STATE_COLLECTED => $this->guardCollected($job),
            default => null,
        };
    }

    private function guardAwaitingApproval(RepairJob $job): void
    {
        $estimate = $job->currentEstimate;

        if ($estimate === null || $estimate->lineItems()->count() === 0) {
            throw new RuntimeException(
                'Cannot send estimate: no line items exist. Add at least one line item first.'
            );
        }
    }

    private function guardCompleted(RepairJob $job): void
    {
        $estimate = $job->currentEstimate;

        if ($estimate === null || ! $estimate->allLineItemsResolved()) {
            throw new RuntimeException(
                'Cannot mark job complete: all line items must be approved or declined first.'
            );
        }
    }

    private function guardCollected(RepairJob $job): void
    {
        if ($job->handoverInspection === null) {
            throw new RuntimeException(
                'Cannot mark as collected: customer handover inspection has not been submitted.'
            );
        }

        if ($job->garage->online_payment_enabled && $job->payment_confirmed_at === null) {
            throw new RuntimeException(
                'Cannot mark as collected: online payment is enabled but payment has not been confirmed.'
            );
        }
    }
}
