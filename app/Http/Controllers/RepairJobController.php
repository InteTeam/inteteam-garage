<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RepairJob\StoreRepairJobRequest;
use App\Http\Requests\RepairJob\UpdateRepairJobRequest;
use App\Models\RepairJob;
use App\Services\RepairJobService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class RepairJobController extends Controller
{
    public function __construct(
        private readonly RepairJobService $repairJobService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', RepairJob::class);

        return Inertia::render('RepairJobs/Index', [
            'repairJobs' => $this->repairJobService->getAll(),
        ]);
    }

    public function store(StoreRepairJobRequest $request): RedirectResponse
    {
        $this->authorize('create', RepairJob::class);

        $this->repairJobService->create($request->validated());

        return redirect()->route('repair-jobs.index')
            ->with(['alert' => 'RepairJob created.', 'type' => 'success']);
    }

    public function show(RepairJob $repairJob): Response
    {
        $this->authorize('view', $repairJob);

        return Inertia::render('RepairJobs/Show', [
            'repairJob' => $repairJob,
        ]);
    }

    public function update(UpdateRepairJobRequest $request, RepairJob $repairJob): RedirectResponse
    {
        $this->authorize('update', $repairJob);

        $this->repairJobService->update($repairJob, $request->validated());

        return redirect()->route('repair-jobs.index')
            ->with(['alert' => 'RepairJob updated.', 'type' => 'success']);
    }

    public function destroy(RepairJob $repairJob): RedirectResponse
    {
        $this->authorize('delete', $repairJob);

        $this->repairJobService->delete($repairJob);

        return redirect()->route('repair-jobs.index')
            ->with(['alert' => 'RepairJob deleted.', 'type' => 'success']);
    }
}
