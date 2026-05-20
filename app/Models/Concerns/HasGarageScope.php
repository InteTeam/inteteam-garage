<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\GarageScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property string $garage_id
 */
trait HasGarageScope
{
    protected static function bootHasGarageScope(): void
    {
        static::addGlobalScope(new GarageScope);

        static::creating(function ($model) {
            if ($model->garage_id === null) {
                $garageId = session('current_garage_id');

                if ($garageId === null && auth()->check()) {
                    /** @var User $authUser */
                    $authUser = auth()->user();
                    $garageId = $authUser->mechanic?->garage_id;
                }

                $model->garage_id = $garageId;
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForGarage(Builder $query, string $garageId): Builder
    {
        return $query->withoutGlobalScope(GarageScope::class)
            ->where('garage_id', $garageId);
    }
}
