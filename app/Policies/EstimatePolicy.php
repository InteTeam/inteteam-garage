<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Estimate;
use App\Models\User;

final class EstimatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->mechanic !== null;
    }

    public function view(User $user, Estimate $estimate): bool
    {
        return $estimate->garage_id === session('current_garage_id');
    }

    public function create(User $user): bool
    {
        return $user->mechanic?->isAdmin() ?? false;
    }

    public function update(User $user, Estimate $estimate): bool
    {
        return $estimate->garage_id === session('current_garage_id')
            && ($user->mechanic?->isAdmin() ?? false);
    }

    public function delete(User $user, Estimate $estimate): bool
    {
        return $estimate->garage_id === session('current_garage_id')
            && ($user->mechanic?->isAdmin() ?? false);
    }
}
