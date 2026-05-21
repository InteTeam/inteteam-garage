<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ApprovalEvent;
use App\Models\Estimate;
use App\Models\HandoverInspection;
use App\Models\HandoverItem;
use App\Models\RepairJob;
use App\Services\ApprovalEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PortalHandoverController extends Controller
{
    public function __construct(
        private readonly ApprovalEventService $approvalEventService,
    ) {}

    public function show(Request $request, string $token): Response
    {
        /** @var RepairJob $job */
        $job = $request->attributes->get('portal_job');

        $job->load(['currentEstimate.lineItems', 'handoverInspection.items']);

        return Inertia::render('Portal/Handover', [
            'job' => $job,
            'token' => $token,
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        /** @var RepairJob $job */
        $job = $request->attributes->get('portal_job');

        if ($job->handoverInspection !== null) {
            return back()->withErrors(['handover' => 'Handover has already been submitted.']);
        }

        /** @var Estimate|null $estimate */
        $estimate = $job->currentEstimate;
        $lineItems = $estimate !== null ? $estimate->lineItems : collect();

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.line_item_id' => ['required', 'ulid'],
            'items.*.accepted' => ['required', 'boolean'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($validated['items'] as $item) {
            if (! $item['accepted'] && empty($item['notes'])) {
                return back()->withErrors([
                    'items' => 'Notes are required when an item is not accepted.',
                ]);
            }
        }

        $inspection = HandoverInspection::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'submitted_by_token' => $token,
            'submitted_at' => now(),
        ]);

        foreach ($validated['items'] as $item) {
            HandoverItem::withoutGlobalScopes()->create([
                'handover_inspection_id' => $inspection->id,
                'line_item_id' => $item['line_item_id'],
                'accepted' => $item['accepted'],
                'notes' => $item['notes'] ?? null,
            ]);
        }

        $this->approvalEventService->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_HANDOVER_SUBMITTED,
            actorType: ApprovalEvent::ACTOR_CUSTOMER,
            payload: ['items_count' => count($validated['items'])],
        );

        return redirect()->route('portal.show', $token)
            ->with(['alert' => 'Handover submitted successfully.', 'type' => 'success']);
    }
}
