<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Garage;
use App\Models\User;

final class GaragePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->mechanic?->isAdmin() ?? false;
    }

    public function view(User $user, Garage $garage): bool
    {
        return $user->mechanic?->garage_id === $garage->id;
    }

    public function update(User $user, Garage $garage): bool
    {
        return $user->mechanic?->garage_id === $garage->id
            && $user->mechanic->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Garage $garage): bool
    {
        return false;
    }
}
