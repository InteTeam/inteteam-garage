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
use RuntimeException;

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

        $this->estimateService->createForJob($job);

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

        try {
            $this->estimateService->update($estimate, $request->validated());
        } catch (RuntimeException $e) {
            // planning.md L175 — once the customer has responded, the estimate is sealed;
            // mechanic must create a new revision. Surface as a validation-style error
            // instead of a 500.
            return back()->withErrors(['estimate' => $e->getMessage()]);
        }

        return back()->with(['alert' => 'The estimate was updated.', 'type' => 'success']);
    }

    public function destroy(RepairJob $job, Estimate $estimate): RedirectResponse
    {
        $this->authorize('delete', $estimate);

        $this->estimateService->delete($estimate);

        return back()->with(['alert' => 'The estimate was deleted.', 'type' => 'success']);
    }
}
