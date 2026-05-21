<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RepairJob;
use App\Models\User;

final class RepairJobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->mechanic !== null;
    }

    public function view(User $user, RepairJob $repairJob): bool
    {
        return $repairJob->garage_id === session('current_garage_id');
    }

    public function create(User $user): bool
    {
        return $user->mechanic?->isAdmin() ?? false;
    }

    public function update(User $user, RepairJob $repairJob): bool
    {
        return $repairJob->garage_id === session('current_garage_id')
            && ($user->mechanic?->isAdmin() ?? false);
    }

    public function delete(User $user, RepairJob $repairJob): bool
    {
        return $repairJob->garage_id === session('current_garage_id')
            && ($user->mechanic?->isAdmin() ?? false);
    }
}
