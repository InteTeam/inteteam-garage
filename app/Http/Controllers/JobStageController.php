<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\JobStage\StoreJobStageRequest;
use App\Http\Requests\JobStage\UpdateJobStageNotesRequest;
use App\Http\Requests\JobStage\UpdateJobStageRequest;
use App\Models\JobStage;
use App\Models\RepairJob;
use App\Models\User;
use App\Services\JobStageService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class JobStageController extends Controller
{
    public function __construct(
        private readonly JobStageService $jobStageService,
    ) {}

    public function index(RepairJob $job): Response
    {
        $this->authorize('view', $job);

        return Inertia::render('JobStages/Index', [
            'job' => $job,
            'jobStages' => $job->stages,
        ]);
    }

    public function store(StoreJobStageRequest $request, RepairJob $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validated();
        $sortOrder = array_search($validated['name'], JobStage::STAGES, true);

        $this->jobStageService->create([
            ...$validated,
            'sort_order' => $sortOrder,
            'job_id' => $job->id,
        ]);

        return back()->with(['alert' => 'The stage was created.', 'type' => 'success']);
    }

    public function show(RepairJob $job, JobStage $stage): Response
    {
        $this->authorize('view', $job);
        $this->ensureStageBelongsToJob($stage, $job);

        return Inertia::render('JobStages/Show', [
            'job' => $job,
            'jobStage' => $stage,
        ]);
    }

    public function update(UpdateJobStageRequest $request, RepairJob $job, JobStage $stage): RedirectResponse
    {
        $this->authorize('update', $job);
        $this->ensureStageBelongsToJob($stage, $job);

        $this->jobStageService->update($stage, $request->validated());

        return back()->with(['alert' => 'The stage was updated.', 'type' => 'success']);
    }

    public function destroy(RepairJob $job, JobStage $stage): RedirectResponse
    {
        $this->authorize('update', $job);
        $this->ensureStageBelongsToJob($stage, $job);

        $this->jobStageService->delete($stage);

        return back()->with(['alert' => 'The stage was deleted.', 'type' => 'success']);
    }

    public function updateNotes(UpdateJobStageNotesRequest $request, RepairJob $job, JobStage $stage): RedirectResponse
    {
        $this->authorize('update', $job);
        $this->ensureStageBelongsToJob($stage, $job);

        /** @var User $user */
        $user = $request->user();
        $mechanic = $user->mechanic;
        abort_if($mechanic === null, 403, 'Only mechanics can edit stage notes.');

        $notes = (string) ($request->validated()['notes'] ?? '');
        $this->jobStageService->updateNotes($stage, $notes, $mechanic);

        return back()->with(['alert' => 'The stage notes were updated.', 'type' => 'success']);
    }

    private function ensureStageBelongsToJob(JobStage $stage, RepairJob $job): void
    {
        abort_if($stage->job_id !== $job->id, 404, 'Stage does not belong to this job.');
    }
}
