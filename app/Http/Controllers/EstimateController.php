<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Estimate\StoreEstimateRequest;
use App\Http\Requests\Estimate\UpdateEstimateRequest;
use App\Models\Estimate;
use App\Models\Garage;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Services\EstimateService;
use App\Services\JobStateMachine;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class EstimateController extends Controller
{
    public function __construct(
        private readonly EstimateService $estimateService,
        private readonly JobStateMachine $stateMachine,
        private readonly TranslationService $translationService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Estimate::class);

        return Inertia::render('Estimates/Index', [
            'estimates' => $this->estimateService->getAll(),
        ]);
    }

    public function store(StoreEstimateRequest $request): RedirectResponse
    {
        $this->authorize('create', Estimate::class);

        $this->estimateService->create($request->validated());

        return redirect()->route('estimates.index')
            ->with(['alert' => 'Estimate created.', 'type' => 'success']);
    }

    public function show(Estimate $estimate): Response
    {
        $this->authorize('view', $estimate);

        return Inertia::render('Estimates/Show', [
            'estimate' => $estimate,
        ]);
    }

    public function update(UpdateEstimateRequest $request, Estimate $estimate): RedirectResponse
    {
        $this->authorize('update', $estimate);

        $this->estimateService->update($estimate, $request->validated());

        return redirect()->route('estimates.index')
            ->with(['alert' => 'Estimate updated.', 'type' => 'success']);
    }

    public function destroy(Estimate $estimate): RedirectResponse
    {
        $this->authorize('delete', $estimate);

        $this->estimateService->delete($estimate);

        return redirect()->route('estimates.index')
            ->with(['alert' => 'Estimate deleted.', 'type' => 'success']);
    }

    public function send(RepairJob $job, Estimate $estimate): RedirectResponse
    {
        $this->authorize('update', $job);

        $this->stateMachine->transition($job, RepairJob::STATE_AWAITING_APPROVAL, (string) auth()->id());

        $estimate->update(['sent_at' => now()]);

        return back()->with(['alert' => 'Estimate sent to customer.', 'type' => 'success']);
    }

    public function previewTranslation(RepairJob $job, Estimate $estimate): JsonResponse
    {
        $this->authorize('view', $job);

        $estimate->load('lineItems');
        $job->load('garage');

        /** @var Garage $garage */
        $garage = $job->garage;
        $garageLocale = $garage->locale;

        $lineItems = $estimate->lineItems->map(fn (LineItem $item) => [
            'id' => $item->id,
            'description' => $item->description,
            'price' => $item->price,
        ])->toArray();

        $translations = $this->translationService->previewEstimateTranslation($lineItems, 'en', $garageLocale);

        return response()->json(['translations' => $translations]);
    }
}
