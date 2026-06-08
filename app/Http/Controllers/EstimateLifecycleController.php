<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Estimate\ConfirmTranslationRequest;
use App\Models\Estimate;
use App\Models\Garage;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Services\CrmApiService;
use App\Services\CrmNotificationService;
use App\Services\EstimateService;
use App\Services\JobStateMachine;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

final class EstimateLifecycleController extends Controller
{
    public function __construct(
        private readonly EstimateService $estimateService,
        private readonly JobStateMachine $stateMachine,
        private readonly TranslationService $translationService,
        private readonly CrmNotificationService $notifications,
        private readonly CrmApiService $crm,
    ) {}

    public function send(RepairJob $job, Estimate $estimate): RedirectResponse
    {
        $this->authorize('update', $job);

        [$fromLocale, $toLocale] = $this->resolveLocalePair($job);

        try {
            $this->estimateService->guardSendable($estimate, $fromLocale, $toLocale);
        } catch (RuntimeException $e) {
            return back()->withErrors(['estimate' => $e->getMessage()]);
        }

        $this->estimateService->markSent($job, $estimate, $this->stateMachine, $this->notifications);

        return back()->with(['alert' => 'The estimate was sent to the customer.', 'type' => 'success']);
    }

    public function previewTranslation(RepairJob $job, Estimate $estimate): JsonResponse
    {
        $this->authorize('view', $job);

        $estimate->load('lineItems');

        [$configuredFrom, $toLocale] = $this->resolveLocalePair($job);

        $sampleText = (string) ($estimate->lineItems->first()->description ?? '');
        $fromLocale = $sampleText !== ''
            ? $this->translationService->verifySourceLocale($configuredFrom, $sampleText)
            : $configuredFrom;

        $lineItems = $estimate->lineItems->map(fn (LineItem $item) => [
            'id' => $item->id,
            'description' => $item->description,
            'price' => $item->price,
        ])->toArray();

        return response()->json([
            'translations' => $this->translationService->previewEstimateTranslation($lineItems, $fromLocale, $toLocale),
            'from_locale' => $fromLocale,
            'to_locale' => $toLocale,
            'configured_from_locale' => $configuredFrom,
            'auto_detected_override' => $fromLocale !== $configuredFrom,
        ]);
    }

    public function confirmTranslation(ConfirmTranslationRequest $request, RepairJob $job, Estimate $estimate): RedirectResponse
    {
        $this->authorize('update', $estimate);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $mechanic = $user->mechanic;
        $mechanicId = $mechanic === null ? '' : $mechanic->id;

        $this->estimateService->confirmTranslation($estimate, $request->validated()['confirmations'], $mechanicId);

        return back()->with(['alert' => 'The translation preview was confirmed.', 'type' => 'success']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveLocalePair(RepairJob $job): array
    {
        $job->load(['garage', 'vehicle']);

        /** @var Garage $garage */
        $garage = $job->garage;
        $garageLocale = $garage->locale;

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $fromLocale = $user->mechanic?->resolvedLocale() ?? $garageLocale;

        $crmCustomerId = (string) ($job->vehicle->crm_customer_id ?? '');
        $toLocale = ($crmCustomerId !== ''
            ? $this->crm->getCustomerLocale($crmCustomerId)
            : null) ?? $garageLocale;

        return [$fromLocale, $toLocale];
    }
}
