<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Garage;
use App\Services\GarageSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GarageSettingsController extends Controller
{
    public function __construct(
        private readonly GarageSettingsService $settingsService,
    ) {}

    public function index(): Response
    {
        $garage = Garage::withoutGlobalScopes()->findOrFail(session('current_garage_id'));
        $this->authorize('view', $garage);

        return Inertia::render('Settings/Index', [
            'garage' => $garage,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $garage = Garage::withoutGlobalScopes()->findOrFail(session('current_garage_id'));
        $this->authorize('update', $garage);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'default_notification_channel' => ['required', 'in:email,sms,in_app'],
            'online_payment_enabled' => ['boolean'],
            'locale' => ['required', 'string', 'in:en,pl'],
        ]);

        $this->settingsService->update($garage, $validated, (string) $request->user()->id);

        return back()->with(['alert' => 'The settings were saved.', 'type' => 'success']);
    }
}
