<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasGarageScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $garage_id
 * @property string $job_id
 * @property string $from_state
 * @property string $to_state
 * @property string|null $transitioned_by
 * @property \Carbon\Carbon $occurred_at
 */
final class JobStateTransition extends Model
{
    use HasGarageScope;
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'garage_id',
        'job_id',
        'from_state',
        'to_state',
        'transitioned_by',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'job_id');
    }
}
