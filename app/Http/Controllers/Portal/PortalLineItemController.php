<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\AskLineItemQuestionRequest;
use App\Http\Requests\Portal\DeclineLineItemRequest;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Services\LineItemDecisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PortalLineItemController extends Controller
{
    public function __construct(
        private readonly LineItemDecisionService $decisions,
    ) {}

    public function approve(Request $request, string $token, LineItem $lineItem): RedirectResponse
    {
        /** @var RepairJob $job */
        $job = $request->attributes->get('portal_job');
        $this->ensureLineItemBelongsToJob($lineItem, $job);

        $this->decisions->approve($job, $lineItem);

        return back()->with(['alert' => 'The item was approved.', 'type' => 'success']);
    }

    public function decline(DeclineLineItemRequest $request, string $token, LineItem $lineItem): RedirectResponse
    {
        /** @var RepairJob $job */
        $job = $request->attributes->get('portal_job');
        $this->ensureLineItemBelongsToJob($lineItem, $job);

        /** @var array{notes: string} $validated */
        $validated = $request->validated();

        $this->decisions->decline($job, $lineItem, $validated['notes']);

        return back()->with(['alert' => 'The item was declined.', 'type' => 'success']);
    }

    public function question(AskLineItemQuestionRequest $request, string $token, LineItem $lineItem): RedirectResponse
    {
        /** @var RepairJob $job */
        $job = $request->attributes->get('portal_job');
        $this->ensureLineItemBelongsToJob($lineItem, $job);

        /** @var array{message: string} $validated */
        $validated = $request->validated();

        $this->decisions->question($job, $lineItem, $validated['message']);

        return back()->with(['alert' => 'The question was sent to the mechanic.', 'type' => 'success']);
    }

    private function ensureLineItemBelongsToJob(LineItem $lineItem, RepairJob $job): void
    {
        $estimate = $job->currentEstimate;

        abort_if(
            $estimate === null || $lineItem->estimate_id !== $estimate->id,
            404,
            'Line item does not belong to this job.'
        );
    }
}
