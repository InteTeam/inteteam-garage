<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ApprovalEvent;
use App\Models\Garage;
use App\Models\NotificationPreference;
use App\Models\RepairJob;
use App\Services\ApprovalEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PortalPreferenceController extends Controller
{
    public function __construct(
        private readonly ApprovalEventService $approvalEventService,
    ) {}

    public function update(Request $request, string $token): RedirectResponse
    {
        /** @var RepairJob $job */
        $job = $request->attributes->get('portal_job');

        $validated = $request->validate([
            'channel' => ['required', 'in:' . implode(',', [Garage::CHANNEL_EMAIL, Garage::CHANNEL_SMS, Garage::CHANNEL_IN_APP])],
        ]);

        $previous = $job->notificationPreference?->channel;

        NotificationPreference::withoutGlobalScopes()->updateOrCreate(
            ['job_id' => $job->id],
            [
                'garage_id' => $job->garage_id,
                'channel' => $validated['channel'],
                'set_by' => 'customer',
            ],
        );

        $this->approvalEventService->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_PREFERENCE_CHANGED,
            actorType: ApprovalEvent::ACTOR_CUSTOMER,
            payload: ['from' => $previous, 'to' => $validated['channel']],
        );

        return back()->with(['alert' => 'Notification preference updated.', 'type' => 'success']);
    }
}
