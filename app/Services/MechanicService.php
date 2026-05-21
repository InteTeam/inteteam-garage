<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Mechanic;
use Illuminate\Database\Eloquent\Collection;

final class MechanicService
{
    public function getAll(): Collection
    {
        return Mechanic::query()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $data): Mechanic
    {
        return Mechanic::create($data);
    }

    public function update(Mechanic $mechanic, array $data): Mechanic
    {
        $mechanic->update($data);

        return $mechanic->fresh();
    }

    public function delete(Mechanic $mechanic): void
    {
        $mechanic->delete();
    }
}
