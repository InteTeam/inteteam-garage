<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Garage\StoreGarageRequest;
use App\Http\Requests\Garage\UpdateGarageRequest;
use App\Models\Garage;
use App\Services\GarageService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class GarageController extends Controller
{
    public function __construct(
        private readonly GarageService $garageService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Garage::class);

        return Inertia::render('Garages/Index', [
            'garages' => $this->garageService->getAll(),
        ]);
    }

    public function store(StoreGarageRequest $request): RedirectResponse
    {
        $this->authorize('create', Garage::class);

        $this->garageService->create($request->validated());

        return redirect()->route('garages.index')
            ->with(['alert' => 'Garage created.', 'type' => 'success']);
    }

    public function show(Garage $garage): Response
    {
        $this->authorize('view', $garage);

        return Inertia::render('Garages/Show', [
            'garage' => $garage,
        ]);
    }

    public function update(UpdateGarageRequest $request, Garage $garage): RedirectResponse
    {
        $this->authorize('update', $garage);

        $this->garageService->update($garage, $request->validated());

        return redirect()->route('garages.index')
            ->with(['alert' => 'Garage updated.', 'type' => 'success']);
    }

    public function destroy(Garage $garage): RedirectResponse
    {
        $this->authorize('delete', $garage);

        $this->garageService->delete($garage);

        return redirect()->route('garages.index')
            ->with(['alert' => 'Garage deleted.', 'type' => 'success']);
    }
}
