<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasGarageScope;
use App\Policies\VehiclePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $garage_id
 * @property string $crm_customer_id
 * @property string $registration
 * @property string|null $vin
 * @property string $make
 * @property string $model
 * @property int|null $year
 * @property string|null $colour
 */
#[UsePolicy(VehiclePolicy::class)]
final class Vehicle extends Model
{
    use HasFactory;
    use HasGarageScope;
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'garage_id',
        'crm_customer_id',
        'registration',
        'vin',
        'make',
        'model',
        'year',
        'colour',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
        ];
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function repairJobs(): HasMany
    {
        return $this->hasMany(RepairJob::class);
    }

    public function complianceRecords(): HasMany
    {
        return $this->hasMany(ComplianceRecord::class);
    }
}
