<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasGarageScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $garage_id
 * @property string $mechanic_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property-read Garage $garage
 * @property-read Mechanic|null $mechanic
 */
final class MechanicOnCall extends Model
{
    use HasFactory;
    use HasGarageScope;
    use HasUlids;

    protected $fillable = [
        'garage_id',
        'mechanic_id',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }

    public function coversNow(): bool
    {
        $now = now();

        return $this->starts_at->lte($now) && $this->ends_at->gte($now);
    }
}
