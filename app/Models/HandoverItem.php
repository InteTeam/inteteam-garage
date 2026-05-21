<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasGarageScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
