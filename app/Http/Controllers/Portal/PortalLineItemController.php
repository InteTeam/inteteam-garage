<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ApprovalEvent;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Services\ApprovalEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class PortalLineItemController extends Controller
{
    public function __construct(
        private readonly ApprovalEventService $approvalEventService,
    ) {}

    public function approve(Request $request, string $token, LineItem $lineItem): RedirectResponse
    {
        /** @var RepairJob $job */
        $job = $request->attributes->get('portal_job');
        $this->ensureLineItemBelongsToJob($lineItem, $job);

        $lineItem->update(['status' => LineItem::STATUS_APPROVED]);

        $this->approvalEventService->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_LINE_ITEM_APPROVED,
            actorType: ApprovalEvent::ACTOR_CUSTOMER,
            payload: ['line_item_id' => $lineItem->id, 'description' => $lineItem->description],
        );

        return back()->with(['alert' => 'Item approved.', 'type' => 'success']);
    }

    public function decline(Request $request, string $token, LineItem $lineItem): RedirectResponse
    {
        /** @var RepairJob $job */
        $job = $request->attributes->get('portal_job');
        $this->ensureLineItemBelongsToJob($lineItem, $job);

        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $lineItem->update([
            'status' => LineItem::STATUS_DECLINED,
            'customer_notes' => $validated['notes'],
        ]);

        $this->approvalEventService->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_LINE_ITEM_DECLINED,
            actorType: ApprovalEvent::ACTOR_CUSTOMER,
            payload: ['line_item_id' => $lineItem->id, 'notes' => $validated['notes']],
        );

        return back()->with(['alert' => 'Item declined.', 'type' => 'success']);
    }

    public function question(Request $request, string $token, LineItem $lineItem): RedirectResponse
    {
        /** @var RepairJob $job */
        $job = $request->attributes->get('portal_job');
        $this->ensureLineItemBelongsToJob($lineItem, $job);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $this->approvalEventService->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_CUSTOMER_QUESTION,
            actorType: ApprovalEvent::ACTOR_CUSTOMER,
            payload: ['line_item_id' => $lineItem->id, 'message' => $validated['message']],
        );

        return back()->with(['alert' => 'Question sent to mechanic.', 'type' => 'success']);
    }

    private function ensureLineItemBelongsToJob(LineItem $lineItem, RepairJob $job): void
    {
        $estimate = $job->currentEstimate;

        if ($estimate === null || $lineItem->estimate_id !== $estimate->id) {
            throw new RuntimeException('Line item does not belong to this job.');
        }
    }
}
