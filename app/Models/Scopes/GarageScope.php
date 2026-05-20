<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\Mechanic;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class GarageScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $garageId = session('current_garage_id');

        if ($garageId === null) {
            /** @var User $user */
            /** @var Mechanic|null $mechanic */
            $mechanic = $user->mechanic;
            $garageId = $mechanic?->garage_id;
        }

        if ($garageId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable() . '.garage_id', $garageId);
    }
}
