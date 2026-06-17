<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SubmitHandoverRequest;
use App\Models\RepairJob;
use App\Services\HandoverInspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PortalHandoverController extends Controller
{
    public function __construct(
        private readonly HandoverInspectionService $handoverService,
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

    public function submit(SubmitHandoverRequest $request, string $token): RedirectResponse
    {
        /** @var RepairJob $job */
        $job = $request->attributes->get('portal_job');

        if ($job->handoverInspection !== null) {
            return back()->withErrors(['handover' => 'Handover has already been submitted.']);
        }

        /** @var array{items: list<array{line_item_id: string, accepted: bool, notes?: string|null}>} $validated */
        $validated = $request->validated();

        foreach ($validated['items'] as $item) {
            if (! $item['accepted'] && empty($item['notes'])) {
                return back()->withErrors([
                    'items' => 'Notes are required when an item is not accepted.',
                ]);
            }
        }

        $this->handoverService->submitFromPortal($job, $validated['items'], $token);

        return redirect()->route('portal.show', $token)
            ->with(['alert' => 'The handover was submitted successfully.', 'type' => 'success']);
    }
}
