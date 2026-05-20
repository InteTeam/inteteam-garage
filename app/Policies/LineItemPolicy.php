<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LineItem;
use App\Models\User;

final class LineItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->mechanic !== null;
    }

    public function view(User $user, LineItem $lineItem): bool
    {
        return $lineItem->garage_id === session('current_garage_id');
    }

    public function create(User $user): bool
    {
        return $user->mechanic?->isAdmin() ?? false;
    }

    public function update(User $user, LineItem $lineItem): bool
    {
        return $lineItem->garage_id === session('current_garage_id')
            && ($user->mechanic?->isAdmin() ?? false);
    }

    public function delete(User $user, LineItem $lineItem): bool
    {
        return $lineItem->garage_id === session('current_garage_id')
            && ($user->mechanic?->isAdmin() ?? false);
    }
}
