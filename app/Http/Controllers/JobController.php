<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RepairJob\StoreJobRequest;
use App\Http\Requests\RepairJob\TransitionJobRequest;
use App\Models\RepairJob;
use App\Services\CrmNotificationService;
use App\Services\JobService;
use App\Services\JobStateMachine;
use App\Services\MechanicService;
use App\Services\VehicleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class JobController extends Controller
{
    public function __construct(
        private readonly JobStateMachine $stateMachine,
        private readonly CrmNotificationService $notifications,
        private readonly VehicleService $vehicles,
        private readonly MechanicService $mechanics,
        private readonly JobService $jobs,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', RepairJob::class);

        return Inertia::render('RepairJobs/Index', [
            'jobs' => $this->jobs->listForIndex(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', RepairJob::class);

        return Inertia::render('RepairJobs/Form', [
            'vehicles' => $this->vehicles->listForJobPicker(),
            'mechanics' => $this->mechanics->listForJobPicker(),
        ]);
    }

    public function store(StoreJobRequest $request): RedirectResponse
    {
        $this->authorize('create', RepairJob::class);

        /** @var array{vehicle_id: string, mechanic_ids: list<string>} $data */
        $data = $request->validated();
        $this->jobs->create($data);

        return redirect()->route('jobs.index')
            ->with(['alert' => 'The job was created.', 'type' => 'success']);
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

    public function transition(TransitionJobRequest $request, RepairJob $job): RedirectResponse
    {
        $this->authorize('update', $job);

        /** @var array{state: string} $validated */
        $validated = $request->validated();

        $this->stateMachine->transition($job, $validated['state'], (string) $request->user()->id);

        if ($validated['state'] === RepairJob::STATE_AWAITING_COLLECTION) {
            $this->notifications->notifyHandoverReady($job->fresh());
        }

        return back()->with(['alert' => 'The job status was updated.', 'type' => 'success']);
    }
}
