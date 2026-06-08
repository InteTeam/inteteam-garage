<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasGarageScope;
use App\Policies\LineItemPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $garage_id
 * @property string $estimate_id
 * @property string $description
 * @property float $price
 * @property string $status
 * @property string|null $customer_notes
 * @property string|null $translation_confirmed_text
 * @property string|null $translation_llm_raw
 * @property string|null $translation_edited_by_mechanic_id
 */
#[UsePolicy(LineItemPolicy::class)]
final class LineItem extends Model
{
    use HasFactory;
    use HasGarageScope;
    use HasUlids;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DECLINED = 'declined';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_DECLINED,
    ];

    protected $fillable = [
        'garage_id',
        'estimate_id',
        'description',
        'price',
        'status',
        'customer_notes',
        'translation_confirmed_text',
        'translation_llm_raw',
        'translation_edited_by_mechanic_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function translationEditor(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class, 'translation_edited_by_mechanic_id');
    }
}
