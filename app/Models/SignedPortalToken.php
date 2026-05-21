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
 * @property string $job_id
 * @property string $token
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 */
final class SignedPortalToken extends Model
{
    use HasFactory;
    use HasGarageScope;
    use HasUlids;

    protected $fillable = [
        'garage_id',
        'job_id',
        'token',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'job_id');
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
