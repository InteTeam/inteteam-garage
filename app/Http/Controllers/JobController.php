<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RepairJob;
use App\Services\JobStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class JobController extends Controller
{
    public function __construct(
        private readonly JobStateMachine $stateMachine,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', RepairJob::class);

        $jobs = RepairJob::with(['vehicle', 'mechanics'])->latest()->get();

        return Inertia::render('RepairJobs/Index', [
            'jobs' => $jobs,
        ]);
    }

    public function show(RepairJob $job): Response
    {
        $this->authorize('view', $job);

        $job->load([
            'vehicle',
            'mechanics',
            'currentEstimate.lineItems',
            'stages',
            'stateTransitions',
            'handoverInspection.items.lineItem',
        ]);

        return Inertia::render('RepairJobs/Show', [
            'job' => $job,
        ]);
    }

    public function transition(Request $request, RepairJob $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'state' => ['required', 'string', 'in:' . implode(',', RepairJob::STATES)],
        ]);

        $this->stateMachine->transition($job, $validated['state'], (string) $request->user()->id);

        return back()->with(['alert' => 'The job status was updated.', 'type' => 'success']);
    }
}
