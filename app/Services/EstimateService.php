<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Estimate;
use Illuminate\Database\Eloquent\Collection;

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
        $estimate->update($data);

        return $estimate->fresh();
    }

    public function delete(Estimate $estimate): void
    {
        $estimate->delete();
    }
}
