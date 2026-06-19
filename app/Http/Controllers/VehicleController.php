<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Services\Dvla\VehicleEnquiryService;
use App\Services\VehicleComplianceService;
use App\Services\VehicleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class VehicleController extends Controller
{
    public function __construct(
        private readonly VehicleService $vehicleService,
        private readonly VehicleComplianceService $complianceService,
        private readonly VehicleEnquiryService $dvlaService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Vehicle::class);

        return Inertia::render('Vehicles/Index', [
            'vehicles' => $this->vehicleService->getAll(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Vehicle::class);

        return Inertia::render('Vehicles/Form', [
            'returningCustomerIds' => $this->vehicleService->getReturningCustomerIds(),
        ]);
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $this->authorize('create', Vehicle::class);

        $this->vehicleService->create($request->validated());

        return redirect()->route('vehicles.index')
            ->with(['alert' => 'The vehicle was created.', 'type' => 'success']);
    }

    public function show(Vehicle $vehicle): Response
    {
        $this->authorize('view', $vehicle);

        return Inertia::render('Vehicles/Show', [
            'vehicle' => $vehicle,
            'compliance' => $this->complianceService->currentForVehicle($vehicle),
            'complianceHistory' => $this->complianceService->historyForVehicle($vehicle),
            'dvlaEnabled' => $this->dvlaService->isConfigured(),
        ]);
    }

    public function edit(Vehicle $vehicle): Response
    {
        $this->authorize('update', $vehicle);

        return Inertia::render('Vehicles/Form', [
            'vehicle' => $vehicle,
            'returningCustomerIds' => $this->vehicleService->getReturningCustomerIds(),
        ]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        $this->vehicleService->update($vehicle, $request->validated());

        return redirect()->route('vehicles.index')
            ->with(['alert' => 'The vehicle was updated.', 'type' => 'success']);
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('delete', $vehicle);

        $this->vehicleService->delete($vehicle);

        return redirect()->route('vehicles.index')
            ->with(['alert' => 'The vehicle was deleted.', 'type' => 'success']);
    }
}
