<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasGarageScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ApprovalEvent extends Model
{
    use HasGarageScope;
    use HasUlids;

    public const ACTOR_MECHANIC = 'mechanic';

    public const ACTOR_CUSTOMER = 'customer';

    public const ACTOR_SYSTEM = 'system';

    public const EVENT_LINE_ITEM_APPROVED = 'line_item_approved';

    public const EVENT_LINE_ITEM_DECLINED = 'line_item_declined';

    public const EVENT_CUSTOMER_QUESTION = 'customer_question';

    public const EVENT_MECHANIC_RESPONSE = 'mechanic_response';

    public const EVENT_ESTIMATE_SENT = 'estimate_sent';

    public const EVENT_SCOPE_CHANGE = 'scope_change';

    public const EVENT_PREFERENCE_CHANGED = 'preference_changed';

    public const EVENT_HANDOVER_SUBMITTED = 'handover_submitted';

    public const EVENT_PAYMENT_REQUESTED = 'payment_requested';

    public const EVENT_PAYMENT_CONFIRMED = 'payment_confirmed';

    public const EVENT_TIMEOUT_ALERT = 'timeout_alert';

    public $timestamps = false;

    protected $fillable = [
        'garage_id',
        'job_id',
        'actor_type',
        'actor_id',
        'event_type',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
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
}
