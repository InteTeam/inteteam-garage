<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasGarageScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $garage_id
 * @property string $job_id
 * @property string $job_stage_id
 * @property string $gcs_path
 * @property string $mime_type
 * @property string $original_filename
 * @property string $uploaded_by
 */
final class Media extends Model
{
    use HasFactory;
    use HasGarageScope;
    use HasUlids;

    protected $fillable = [
        'garage_id',
        'job_id',
        'job_stage_id',
        'gcs_path',
        'mime_type',
        'original_filename',
        'uploaded_by',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
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

    public function jobStage(): BelongsTo
    {
        return $this->belongsTo(JobStage::class);
    }
}
