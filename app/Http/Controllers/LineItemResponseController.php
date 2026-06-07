<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApprovalEvent;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Services\ApprovalEventService;
use App\Services\CrmNotificationService;
use App\Services\JobStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class LineItemResponseController extends Controller
{
    public function __construct(
        private readonly ApprovalEventService $approvalEventService,
        private readonly JobStateMachine $stateMachine,
        private readonly CrmNotificationService $notifications,
    ) {}

    public function store(Request $request, RepairJob $job, LineItem $lineItem): RedirectResponse
    {
        $this->authorize('update', $job);
        $this->ensureLineItemBelongsToJob($lineItem, $job);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $actorId = (string) $request->user()->id;

        $this->approvalEventService->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_MECHANIC_RESPONSE,
            actorType: ApprovalEvent::ACTOR_MECHANIC,
            actorId: $actorId,
            payload: ['line_item_id' => $lineItem->id, 'message' => $validated['message']],
        );

        if ($job->state === RepairJob::STATE_CUSTOMER_QUERY) {
            $this->stateMachine->transition($job, RepairJob::STATE_AWAITING_APPROVAL, $actorId);
        }

        $this->notifications->notifyMechanicResponse($job->fresh(), $validated['message']);

        return back()->with(['alert' => 'The response was sent to the customer.', 'type' => 'success']);
    }

    private function ensureLineItemBelongsToJob(LineItem $lineItem, RepairJob $job): void
    {
        $estimate = $job->currentEstimate;

        if ($estimate === null || $lineItem->estimate_id !== $estimate->id) {
            throw new RuntimeException('Line item does not belong to this job.');
        }
    }
}
