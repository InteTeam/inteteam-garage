<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Garage;
use Illuminate\Database\Eloquent\Collection;

final class GarageService
{
    public function getAll(): Collection
    {
        return Garage::query()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $data): Garage
    {
        return Garage::create($data);
    }

    public function update(Garage $garage, array $data): Garage
    {
        $garage->update($data);

        return $garage->fresh();
    }

    public function delete(Garage $garage): void
    {
        $garage->delete();
    }
}
