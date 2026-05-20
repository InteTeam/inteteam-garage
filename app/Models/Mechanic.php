<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasGarageScope;
use App\Policies\MechanicPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $garage_id
 * @property string $user_id
 * @property string $role
 * @property bool $is_active
 */
#[UsePolicy(MechanicPolicy::class)]
final class Mechanic extends Model
{
    use HasFactory;
    use HasGarageScope;
    use HasUlids;
    use SoftDeletes;

    public const ROLE_GARAGE_ADMIN = 'garage_admin';

    public const ROLE_MECHANIC = 'mechanic';

    public const ROLES = [
        self::ROLE_GARAGE_ADMIN,
        self::ROLE_MECHANIC,
    ];

    protected $fillable = [
        'garage_id',
        'user_id',
        'role',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function repairJobs(): BelongsToMany
    {
        return $this->belongsToMany(RepairJob::class, 'repair_job_mechanic');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_GARAGE_ADMIN;
    }
}
