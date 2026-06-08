<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Estimate\StoreEstimateRequest;
use App\Http\Requests\Estimate\UpdateEstimateRequest;
use App\Models\Estimate;
use App\Models\RepairJob;
use App\Services\EstimateService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class EstimateController extends Controller
{
    public function __construct(
        private readonly EstimateService $estimateService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Estimate::class);

        return Inertia::render('Estimates/Index', [
            'estimates' => $this->estimateService->getAll(),
        ]);
    }

    public function store(StoreEstimateRequest $request, RepairJob $job): RedirectResponse
    {
        $this->authorize('create', Estimate::class);

        $nextRevision = ($job->estimates()->max('revision_number') ?? 0) + 1;

        $this->estimateService->create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'revision_number' => $nextRevision,
        ]);

        return back()->with(['alert' => 'The estimate was created.', 'type' => 'success']);
    }

    public function show(RepairJob $job, Estimate $estimate): Response
    {
        $this->authorize('view', $estimate);

        return Inertia::render('Estimates/Show', [
            'estimate' => $estimate,
        ]);
    }

    public function update(UpdateEstimateRequest $request, RepairJob $job, Estimate $estimate): RedirectResponse
    {
        $this->authorize('update', $estimate);

        $this->estimateService->update($estimate, $request->validated());

        return back()->with(['alert' => 'The estimate was updated.', 'type' => 'success']);
    }

    public function destroy(RepairJob $job, Estimate $estimate): RedirectResponse
    {
        $this->authorize('delete', $estimate);

        $this->estimateService->delete($estimate);

        return back()->with(['alert' => 'The estimate was deleted.', 'type' => 'success']);
    }
}
