<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApprovalEvent;
use App\Models\Garage;
use App\Models\NotificationPreference;
use App\Models\RepairJob;
use App\Services\ApprovalEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class JobNotificationPreferenceController extends Controller
{
    public function __construct(
        private readonly ApprovalEventService $approvalEventService,
    ) {}

    public function update(Request $request, RepairJob $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'channel' => ['required', 'in:' . implode(',', Garage::CHANNELS)],
        ]);

        $previous = $job->notificationPreference?->channel;

        if ($previous === $validated['channel']) {
            return back()->with(['alert' => 'The notification preference was unchanged.', 'type' => 'info']);
        }

        NotificationPreference::withoutGlobalScopes()->updateOrCreate(
            ['job_id' => $job->id],
            [
                'garage_id' => $job->garage_id,
                'channel' => $validated['channel'],
                'set_by' => 'admin',
            ],
        );

        $this->approvalEventService->record(
            job: $job,
            eventType: ApprovalEvent::EVENT_PREFERENCE_CHANGED,
            actorType: ApprovalEvent::ACTOR_MECHANIC,
            actorId: (string) $request->user()->id,
            payload: ['from' => $previous, 'to' => $validated['channel']],
        );

        return back()->with(['alert' => 'The notification preference was updated.', 'type' => 'success']);
    }
}
