<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalEvent;
use App\Models\LineItem;
use App\Models\RepairJob;

/**
 * Customer-side decisions on individual estimate line items, made via the
 * portal: approve, decline (with notes), or ask a follow-up question. Each
 * decision writes the line item state (where applicable) and emits the
 * matching audit event in lockstep.
 */
final class LineItemDecisionService
{
    public function __construct(
        private readonly ApprovalEventService $approvalEventService,
    ) {}

    public function approve(RepairJob $job, LineItem $lineItem): void
    {
        $lineItem->update(['status' => LineItem::STATUS_APPROVED]);

        $this->approvalEventService->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_LINE_ITEM_APPROVED,
            actorType: ApprovalEvent::ACTOR_CUSTOMER,
            payload: ['line_item_id' => $lineItem->id, 'description' => $lineItem->description],
        );
    }

    public function decline(RepairJob $job, LineItem $lineItem, string $notes): void
    {
        $lineItem->update([
            'status' => LineItem::STATUS_DECLINED,
            'customer_notes' => $notes,
        ]);

        $this->approvalEventService->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_LINE_ITEM_DECLINED,
            actorType: ApprovalEvent::ACTOR_CUSTOMER,
            payload: ['line_item_id' => $lineItem->id, 'notes' => $notes],
        );
    }

    public function question(RepairJob $job, LineItem $lineItem, string $message): void
    {
        $this->approvalEventService->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_CUSTOMER_QUESTION,
            actorType: ApprovalEvent::ACTOR_CUSTOMER,
            payload: ['line_item_id' => $lineItem->id, 'message' => $message],
        );
    }
}
