<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasGarageScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $garage_id
 * @property string $job_id
 * @property string $name
 * @property string|null $notes
 * @property string|null $notes_translated
 * @property string|null $notes_source_locale
 * @property string|null $notes_target_locale
 * @property Carbon|null $notes_translated_at
 * @property int $sort_order
 * @property Carbon|null $locked_at
 * @property-read Collection<int, Media> $media
 * @property-read Garage $garage
 * @property-read RepairJob $repairJob
 */
final class JobStage extends Model
{
    use HasFactory;
    use HasGarageScope;
    use HasUlids;
    use SoftDeletes;

    public const STAGE_PRE_INSPECTION = 'pre-inspection';

    public const STAGE_DISASSEMBLY = 'disassembly';

    public const STAGE_FAULT_FOUND = 'fault-found';

    public const STAGE_REPAIR = 'repair';

    public const STAGE_COMPLETE = 'complete';

    public const STAGES = [
        self::STAGE_PRE_INSPECTION,
        self::STAGE_DISASSEMBLY,
        self::STAGE_FAULT_FOUND,
        self::STAGE_REPAIR,
        self::STAGE_COMPLETE,
    ];

    protected $fillable = [
        'garage_id',
        'job_id',
        'name',
        'notes',
        'notes_translated',
        'notes_source_locale',
        'notes_target_locale',
        'notes_translated_at',
        'sort_order',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'locked_at' => 'datetime',
            'notes_translated_at' => 'datetime',
        ];
    }

    public function notesWereTranslatedByAi(): bool
    {
        return $this->notes_translated !== null
            && $this->notes_source_locale !== $this->notes_target_locale;
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'job_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
