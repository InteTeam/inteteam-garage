<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

final class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->mechanic !== null;
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $vehicle->garage_id === session('current_garage_id');
    }

    public function create(User $user): bool
    {
        return $user->mechanic?->isAdmin() ?? false;
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $vehicle->garage_id === session('current_garage_id')
            && ($user->mechanic?->isAdmin() ?? false);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $vehicle->garage_id === session('current_garage_id')
            && ($user->mechanic?->isAdmin() ?? false);
    }
}
