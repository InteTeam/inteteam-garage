<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LineItem\PreviewLineItemResponseRequest;
use App\Http\Requests\LineItem\StoreLineItemResponseRequest;
use App\Models\ApprovalEvent;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Models\User;
use App\Services\ApprovalEventService;
use App\Services\CrmApiService;
use App\Services\CrmNotificationService;
use App\Services\JobStateMachine;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class LineItemResponseController extends Controller
{
    public function __construct(
        private readonly ApprovalEventService $approvalEventService,
        private readonly JobStateMachine $stateMachine,
        private readonly CrmNotificationService $notifications,
        private readonly TranslationService $translation,
        private readonly CrmApiService $crm,
    ) {}

    public function store(StoreLineItemResponseRequest $request, RepairJob $job, LineItem $lineItem): RedirectResponse
    {
        $this->authorize('update', $job);
        $this->ensureLineItemBelongsToJob($lineItem, $job);

        /** @var array{message: string, translated_message?: string|null} $validated */
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();
        [$fromLocale, $toLocale] = $this->translation->resolveLocalePairForJob($job, $user, $this->crm);

        if ($fromLocale !== $toLocale && empty($validated['translated_message'] ?? null)) {
            throw ValidationException::withMessages([
                'translated_message' => 'A confirmed translation is required when mechanic and customer locales differ.',
            ]);
        }

        $actorId = (string) $user->id;

        $payload = [
            'line_item_id' => $lineItem->id,
            'message' => $validated['message'],
            'from_locale' => $fromLocale,
            'to_locale' => $toLocale,
        ];

        if (! empty($validated['translated_message'] ?? null)) {
            $payload['translated_message'] = $validated['translated_message'];
        }

        $this->approvalEventService->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_MECHANIC_RESPONSE,
            actorType: ApprovalEvent::ACTOR_MECHANIC,
            actorId: $actorId,
            payload: $payload,
        );

        if ($job->state === RepairJob::STATE_CUSTOMER_QUERY) {
            $this->stateMachine->transition($job, RepairJob::STATE_AWAITING_APPROVAL, $actorId);
        }

        $messageForNotification = $validated['translated_message'] ?? $validated['message'];
        $this->notifications->notifyMechanicResponse($job->fresh(), $messageForNotification);

        return back()->with(['alert' => 'The response was sent to the customer.', 'type' => 'success']);
    }

    public function preview(PreviewLineItemResponseRequest $request, RepairJob $job, LineItem $lineItem): JsonResponse
    {
        $this->authorize('update', $job);
        $this->ensureLineItemBelongsToJob($lineItem, $job);

        /** @var array{message: string} $validated */
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();
        [$configuredFrom, $toLocale] = $this->translation->resolveLocalePairForJob($job, $user, $this->crm);

        $fromLocale = $this->translation->verifySourceLocale($configuredFrom, $validated['message']);

        $translated = $fromLocale === $toLocale
            ? $validated['message']
            : $this->translation->translate($validated['message'], $fromLocale, $toLocale, 'mechanic_response');

        return response()->json([
            'original' => $validated['message'],
            'translated' => $translated,
            'from_locale' => $fromLocale,
            'to_locale' => $toLocale,
            'configured_from_locale' => $configuredFrom,
            'auto_detected_override' => $fromLocale !== $configuredFrom,
            'translated_by_ai' => $translated !== $validated['message'],
        ]);
    }

    private function ensureLineItemBelongsToJob(LineItem $lineItem, RepairJob $job): void
    {
        $estimate = $job->currentEstimate;

        abort_if(
            $estimate === null || $lineItem->estimate_id !== $estimate->id,
            404,
            'Line item does not belong to this job.'
        );
    }
}
