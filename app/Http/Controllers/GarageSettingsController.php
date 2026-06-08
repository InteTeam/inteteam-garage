<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Garage\UpdateGarageSettingsRequest;
use App\Models\Garage;
use App\Services\GarageSettingsService;
use Illuminate\Http\RedirectResponse;
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

    public function update(UpdateGarageSettingsRequest $request): RedirectResponse
    {
        $garage = Garage::withoutGlobalScopes()->findOrFail(session('current_garage_id'));
        $this->authorize('update', $garage);

        $this->settingsService->update($garage, $request->validated(), (string) $request->user()->id);

        return back()->with(['alert' => 'The settings were saved.', 'type' => 'success']);
    }
}
