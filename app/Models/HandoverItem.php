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
 * @property string $handover_inspection_id
 * @property string $line_item_id
 * @property bool $accepted
 * @property string|null $notes
 * @property-read Garage $garage
 * @property-read HandoverInspection $handoverInspection
 * @property-read LineItem $lineItem
 */
final class HandoverItem extends Model
{
    use HasFactory;
    use HasGarageScope;
    use HasUlids;

    protected $fillable = [
        'garage_id',
        'handover_inspection_id',
        'line_item_id',
        'accepted',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'accepted' => 'boolean',
        ];
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function handoverInspection(): BelongsTo
    {
        return $this->belongsTo(HandoverInspection::class);
    }

    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(LineItem::class);
    }
}
