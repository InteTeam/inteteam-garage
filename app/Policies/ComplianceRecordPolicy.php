<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ComplianceRecord;
use App\Models\User;
use App\Models\Vehicle;

final class ComplianceRecordPolicy
{
    public function view(User $user, ComplianceRecord $record): bool
    {
        return $record->garage_id === session('current_garage_id');
    }

    public function createForVehicle(User $user, Vehicle $vehicle): bool
    {
        return $vehicle->garage_id === session('current_garage_id')
            && ($user->mechanic?->isAdmin() ?? false);
    }
}
