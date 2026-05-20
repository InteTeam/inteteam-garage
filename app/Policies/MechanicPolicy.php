<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Mechanic;
use App\Models\User;

final class MechanicPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->mechanic !== null;
    }

    public function view(User $user, Mechanic $mechanic): bool
    {
        return $mechanic->garage_id === session('current_garage_id');
    }

    public function create(User $user): bool
    {
        return $user->mechanic?->isAdmin() ?? false;
    }

    public function update(User $user, Mechanic $mechanic): bool
    {
        return $mechanic->garage_id === session('current_garage_id')
            && ($user->mechanic?->isAdmin() ?? false);
    }

    public function delete(User $user, Mechanic $mechanic): bool
    {
        return $mechanic->garage_id === session('current_garage_id')
            && ($user->mechanic?->isAdmin() ?? false);
    }
}
