<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ComplianceSource;
use App\Enums\ComplianceType;
use App\Models\Concerns\HasGarageScope;
use App\Policies\ComplianceRecordPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $garage_id
 * @property string $vehicle_id
 * @property int|null $recorded_by_user_id
 * @property ComplianceType $type
 * @property ComplianceSource $source
 * @property Carbon $expires_on
 * @property string|null $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[UsePolicy(ComplianceRecordPolicy::class)]
final class ComplianceRecord extends Model
{
    use HasFactory;
    use HasGarageScope;
    use HasUlids;

    protected $fillable = [
        'garage_id',
        'vehicle_id',
        'recorded_by_user_id',
        'type',
        'source',
        'expires_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => ComplianceType::class,
            'source' => ComplianceSource::class,
            'expires_on' => 'date',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
