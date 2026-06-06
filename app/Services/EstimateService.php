<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Estimate;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

final class EstimateService
{
    public function getAll(): Collection
    {
        return Estimate::query()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $data): Estimate
    {
        return Estimate::create($data);
    }

    public function update(Estimate $estimate, array $data): Estimate
    {
        if ($estimate->hasCustomerResponse()) {
            throw new RuntimeException(
                'Cannot modify estimate after customer response. Create a new revision instead.'
            );
        }

        $estimate->update($data);

        return $estimate->fresh();
    }

    public function delete(Estimate $estimate): void
    {
        $estimate->delete();
    }
}
