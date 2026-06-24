<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AskLineItemQuestionRequest;
use App\Http\Requests\Customer\DeclineLineItemRequest;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Models\Vehicle;
use App\Services\LineItemDecisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class LineItemController extends Controller
{
    public function __construct(
        private readonly LineItemDecisionService $decisions,
    ) {}

    public function approve(string $jobId, string $lineItemId): RedirectResponse
    {
        [$customer, $job, $lineItem] = $this->resolveOwned($jobId, $lineItemId);

        $this->decisions->approve($job, $lineItem, actorId: $customer->id);

        return back()->with(['alert' => 'The item was approved.', 'type' => 'success']);
    }

    public function decline(DeclineLineItemRequest $request, string $jobId, string $lineItemId): RedirectResponse
    {
        [$customer, $job, $lineItem] = $this->resolveOwned($jobId, $lineItemId);

        /** @var array{notes: string} $validated */
        $validated = $request->validated();

        $this->decisions->decline($job, $lineItem, $validated['notes'], actorId: $customer->id);

        return back()->with(['alert' => 'The item was declined.', 'type' => 'success']);
    }

    public function question(AskLineItemQuestionRequest $request, string $jobId, string $lineItemId): RedirectResponse
    {
        [$customer, $job, $lineItem] = $this->resolveOwned($jobId, $lineItemId);

        /** @var array{message: string} $validated */
        $validated = $request->validated();

        $this->decisions->question($job, $lineItem, $validated['message'], actorId: $customer->id);

        return back()->with(['alert' => 'The question was sent to the mechanic.', 'type' => 'success']);
    }

    /**
     * Triple ownership guard: customer must be CRM-linked, job's vehicle must
     * belong to that customer, and the line item must be on the job's current
     * estimate. Any failure → 404 (not 403, per portal convention). State
     * mismatch (e.g. customer POSTing to a collected job) → 409 — separate
     * from ownership so the audit trail can distinguish "guess attack on a
     * stranger's job" from "stale tab posting to a finished job."
     *
     * @return array{0: Customer, 1: RepairJob, 2: LineItem}
     */
    private function resolveOwned(string $jobId, string $lineItemId): array
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        if (! $customer->isLinkedToCrm()) {
            abort(404);
        }

        // Two-step ownership check instead of whereHas — the latter applies
        // the related model's HasGarageScope inside the EXISTS subquery, and
        // withoutGlobalScopes() inside the closure is unreliable. Resolve the
        // job unscoped, then verify ownership via the vehicle row directly.
        /** @var RepairJob $job */
        $job = RepairJob::withoutGlobalScopes()
            ->where('id', $jobId)
            ->firstOrFail();

        $ownsVehicle = Vehicle::withoutGlobalScopes()
            ->where('id', $job->vehicle_id)
            ->where('crm_customer_id', $customer->crm_customer_id)
            ->exists();

        abort_unless($ownsVehicle, 404);

        // Bypass HasGarageScope on Estimate entirely — `currentEstimate()`
        // relation builder + `latestOfMany` makes withoutGlobalScopes inside
        // the relation unreliable, so query Estimate directly.
        $estimate = Estimate::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->orderByDesc('revision_number')
            ->first();

        abort_if($estimate === null, 404);

        /** @var LineItem $lineItem */
        $lineItem = LineItem::withoutGlobalScopes()
            ->where('id', $lineItemId)
            ->where('estimate_id', $estimate->id)
            ->firstOrFail();

        // State gate AFTER ownership: 409 for "wrong moment" only leaks for
        // jobs the customer already owns; foreign jobs still 404 above.
        abort_unless(
            in_array($job->state, LineItemDecisionService::ACTIONABLE_STATES, true),
            409,
            'This job is no longer accepting line-item decisions.',
        );

        return [$customer, $job, $lineItem];
    }
}
