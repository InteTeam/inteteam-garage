<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\NotificationPreference\StoreNotificationPreferenceRequest;
use App\Http\Requests\NotificationPreference\UpdateNotificationPreferenceRequest;
use App\Models\NotificationPreference;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationPreferenceController extends Controller
{
    public function __construct(
        private readonly NotificationPreferenceService $notificationPreferenceService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', NotificationPreference::class);

        return Inertia::render('NotificationPreferences/Index', [
            'notificationPreferences' => $this->notificationPreferenceService->getAll(),
        ]);
    }

    public function store(StoreNotificationPreferenceRequest $request): RedirectResponse
    {
        $this->authorize('create', NotificationPreference::class);

        $this->notificationPreferenceService->create($request->validated());

        return redirect()->route('notification-preferences.index')
            ->with(['alert' => 'NotificationPreference created.', 'type' => 'success']);
    }

    public function show(NotificationPreference $notificationPreference): Response
    {
        $this->authorize('view', $notificationPreference);

        return Inertia::render('NotificationPreferences/Show', [
            'notificationPreference' => $notificationPreference,
        ]);
    }

    public function update(UpdateNotificationPreferenceRequest $request, NotificationPreference $notificationPreference): RedirectResponse
    {
        $this->authorize('update', $notificationPreference);

        $this->notificationPreferenceService->update($notificationPreference, $request->validated());

        return redirect()->route('notification-preferences.index')
            ->with(['alert' => 'NotificationPreference updated.', 'type' => 'success']);
    }

    public function destroy(NotificationPreference $notificationPreference): RedirectResponse
    {
        $this->authorize('delete', $notificationPreference);

        $this->notificationPreferenceService->delete($notificationPreference);

        return redirect()->route('notification-preferences.index')
            ->with(['alert' => 'NotificationPreference deleted.', 'type' => 'success']);
    }
}
