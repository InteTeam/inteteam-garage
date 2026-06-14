<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

final class VehicleService
{
    public function getAll(): Collection
    {
        return Vehicle::query()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Slim vehicle list for the job-create picker. Avoids hydrating
     * full models in the controller.
     */
    public function listForJobPicker(): Collection
    {
        return Vehicle::query()
            ->orderBy('registration')
            ->get(['id', 'registration', 'make', 'model']);
    }

    /**
     * Distinct crm_customer_ids previously used by this garage's vehicles.
     * Surfaced in the vehicle form as a datalist so returning customers
     * are one click instead of a copy-paste round trip to CRM.
     *
     * @return SupportCollection<int, string>
     */
    public function getReturningCustomerIds(): SupportCollection
    {
        return Vehicle::query()
            ->select('crm_customer_id')
            ->distinct()
            ->orderBy('crm_customer_id')
            ->pluck('crm_customer_id');
    }

    public function create(array $data): Vehicle
    {
        return Vehicle::create($data);
    }

    public function update(Vehicle $vehicle, array $data): Vehicle
    {
        $vehicle->update($data);

        return $vehicle->fresh();
    }

    public function delete(Vehicle $vehicle): void
    {
        $vehicle->delete();
    }
}
