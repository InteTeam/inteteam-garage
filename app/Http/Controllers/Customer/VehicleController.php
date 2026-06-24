<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\VehicleComplianceService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class VehicleController extends Controller
{
    public function __construct(
        private readonly VehicleComplianceService $complianceService,
    ) {}

    public function show(string $vehicleId): Response
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        // 404 — never 403 — when ownership fails. Leaking the existence of a
        // resource is a smaller hit than telling an unauthorised browser
        // that a given vehicle exists in our system.
        $vehicle = Vehicle::withoutGlobalScopes()
            ->where('id', $vehicleId)
            ->when(
                $customer->isLinkedToCrm(),
                fn ($q) => $q->where('crm_customer_id', $customer->crm_customer_id),
                fn ($q) => $q->whereRaw('1 = 0'),
            )
            ->firstOrFail();

        return Inertia::render('Account/VehicleShow', [
            'vehicle' => [
                'id' => $vehicle->id,
                'registration' => $vehicle->registration,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'colour' => $vehicle->colour,
                'vin' => $vehicle->vin,
            ],
            'compliance' => $this->complianceService->currentForVehicle($vehicle),
            'complianceHistory' => $this->complianceService->historyForVehicle($vehicle),
        ]);
    }
}
