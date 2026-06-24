<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Garage;
use App\Models\RepairJob;
use App\Models\Vehicle;
use App\Services\VehicleComplianceService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly VehicleComplianceService $complianceService,
    ) {}

    public function index(): Response
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        // Customer that hasn't been matched to CRM yet — render a banner-only
        // state. We don't reveal any jobs/vehicles until the CRM link exists.
        if (! $customer->isLinkedToCrm()) {
            return Inertia::render('Account/Dashboard', [
                'customer' => $this->customerSummary($customer),
                'linked' => false,
                'vehicles' => [],
                'recentJobs' => [],
            ]);
        }

        // Cross-tenant queries: customer can have vehicles/jobs in multiple
        // garages. withoutGlobalScopes() drops the garage filter that the
        // mechanic dashboard relies on.
        $vehicles = Vehicle::withoutGlobalScopes()
            ->where('crm_customer_id', $customer->crm_customer_id)
            ->orderBy('registration')
            ->get();

        $compliancePerVehicle = $vehicles->mapWithKeys(
            fn (Vehicle $v) => [$v->id => $this->complianceService->currentForVehicle($v)],
        );

        $recentJobs = RepairJob::withoutGlobalScopes()
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            // closure-based eager load: Vehicle uses HasGarageScope and the
            // customer guard has no current_garage_id session, so the default
            // scope would filter every vehicle out and crash on null access.
            ->with(['vehicle' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'registration', 'make', 'model')])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $garageNames = Garage::withoutGlobalScopes()
            ->whereIn('id', $recentJobs->pluck('garage_id')->merge($vehicles->pluck('garage_id'))->unique())
            ->pluck('name', 'id');

        return Inertia::render('Account/Dashboard', [
            'customer' => $this->customerSummary($customer),
            'linked' => true,
            'vehicles' => $vehicles->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'registration' => $v->registration,
                'make' => $v->make,
                'model' => $v->model,
                'garage_name' => $garageNames[$v->garage_id] ?? null,
                'compliance' => collect($compliancePerVehicle[$v->id])->map(fn ($record) => $record === null ? null : [
                    'expires_on' => $record->expires_on->toDateString(),
                ])->all(),
            ])->values(),
            'recentJobs' => $recentJobs->map(fn (RepairJob $j) => [
                'id' => $j->id,
                'state' => $j->state,
                'updated_at' => $j->updated_at?->toIso8601String(),
                'vehicle' => [
                    'registration' => $j->vehicle->registration,
                    'make' => $j->vehicle->make,
                    'model' => $j->vehicle->model,
                ],
                'garage_name' => $garageNames[$j->garage_id] ?? null,
            ])->values(),
        ]);
    }

    /**
     * @return array{name: string, email: string}
     */
    private function customerSummary(Customer $customer): array
    {
        return [
            'name' => $customer->name,
            'email' => $customer->email,
        ];
    }
}
