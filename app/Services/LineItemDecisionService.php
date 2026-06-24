<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalEvent;
use App\Models\LineItem;
use App\Models\RepairJob;
use RuntimeException;

/**
 * Customer-side decisions on individual estimate line items, made via the
 * portal: approve, decline (with notes), or ask a follow-up question. Each
 * decision writes the line item state (where applicable) and emits the
 * matching audit event in lockstep.
 */
final class LineItemDecisionService
{
    /**
     * Job states in which a customer may act on line items. Outside these
     * states the estimate is either still being built (in_progress, booked)
     * or already settled (approved, completed, awaiting_collection, collected)
     * — either way, customer input must not mutate state or audit log.
     */
    public const ACTIONABLE_STATES = [
        RepairJob::STATE_AWAITING_APPROVAL,
        RepairJob::STATE_CUSTOMER_QUERY,
    ];

    public function __construct(
        private readonly ApprovalEventService $approvalEventService,
    ) {}

    public function approve(RepairJob $job, LineItem $lineItem, string $actorId): void
    {
        $this->ensureJobAcceptsCustomerDecisions($job);

        $lineItem->update(['status' => LineItem::STATUS_APPROVED]);

        $this->approvalEventService->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_LINE_ITEM_APPROVED,
            actorType: ApprovalEvent::ACTOR_CUSTOMER,
            actorId: $actorId,
            payload: ['line_item_id' => $lineItem->id, 'description' => $lineItem->description],
        );
    }

    public function decline(RepairJob $job, LineItem $lineItem, string $notes, string $actorId): void
    {
        $this->ensureJobAcceptsCustomerDecisions($job);

        $lineItem->update([
            'status' => LineItem::STATUS_DECLINED,
            'customer_notes' => $notes,
        ]);

        $this->approvalEventService->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_LINE_ITEM_DECLINED,
            actorType: ApprovalEvent::ACTOR_CUSTOMER,
            actorId: $actorId,
            payload: ['line_item_id' => $lineItem->id, 'notes' => $notes],
        );
    }

    public function question(RepairJob $job, LineItem $lineItem, string $message, string $actorId): void
    {
        $this->ensureJobAcceptsCustomerDecisions($job);

        $this->approvalEventService->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_CUSTOMER_QUESTION,
            actorType: ApprovalEvent::ACTOR_CUSTOMER,
            actorId: $actorId,
            payload: ['line_item_id' => $lineItem->id, 'message' => $message],
        );
    }

    /**
     * Defense-in-depth state gate. Controllers do their own ownership checks,
     * but the service is the single source of truth for "is this job still
     * accepting customer input" — the frontend's isPending UI hint is not a
     * security boundary.
     */
    private function ensureJobAcceptsCustomerDecisions(RepairJob $job): void
    {
        if (! in_array($job->state, self::ACTIONABLE_STATES, true)) {
            throw new RuntimeException(
                "Customer cannot act on line items: job is in [{$job->state}], not awaiting customer decision."
            );
        }
    }
}
