<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasGarageScope;
use App\Policies\EstimatePolicy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
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
 * @property int $revision_number
 * @property Carbon|null $sent_at
 * @property Carbon|null $preview_confirmed_at
 * @property Collection<int, LineItem> $lineItems
 */
#[UsePolicy(EstimatePolicy::class)]
final class Estimate extends Model
{
    use HasFactory;
    use HasGarageScope;
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'garage_id',
        'job_id',
        'revision_number',
        'sent_at',
        'preview_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'sent_at' => 'datetime',
            'preview_confirmed_at' => 'datetime',
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

    public function lineItems(): HasMany
    {
        return $this->hasMany(LineItem::class);
    }

    public function hasCustomerResponse(): bool
    {
        return $this->lineItems()->whereIn('status', [LineItem::STATUS_APPROVED, LineItem::STATUS_DECLINED])->exists();
    }

    public function allLineItemsResolved(): bool
    {
        return ! $this->lineItems()->where('status', LineItem::STATUS_PENDING)->exists();
    }
}
