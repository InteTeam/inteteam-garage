<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\JobStage\StoreJobStageRequest;
use App\Http\Requests\JobStage\UpdateJobStageRequest;
use App\Models\JobStage;
use App\Services\JobStageService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class JobStageController extends Controller
{
    public function __construct(
        private readonly JobStageService $jobStageService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', JobStage::class);

        return Inertia::render('JobStages/Index', [
            'jobStages' => $this->jobStageService->getAll(),
        ]);
    }

    public function store(StoreJobStageRequest $request): RedirectResponse
    {
        $this->authorize('create', JobStage::class);

        $this->jobStageService->create($request->validated());

        return redirect()->route('job-stages.index')
            ->with(['alert' => 'JobStage created.', 'type' => 'success']);
    }

    public function show(JobStage $jobStage): Response
    {
        $this->authorize('view', $jobStage);

        return Inertia::render('JobStages/Show', [
            'jobStage' => $jobStage,
        ]);
    }

    public function update(UpdateJobStageRequest $request, JobStage $jobStage): RedirectResponse
    {
        $this->authorize('update', $jobStage);

        $this->jobStageService->update($jobStage, $request->validated());

        return redirect()->route('job-stages.index')
            ->with(['alert' => 'JobStage updated.', 'type' => 'success']);
    }

    public function destroy(JobStage $jobStage): RedirectResponse
    {
        $this->authorize('delete', $jobStage);

        $this->jobStageService->delete($jobStage);

        return redirect()->route('job-stages.index')
            ->with(['alert' => 'JobStage deleted.', 'type' => 'success']);
    }
}
