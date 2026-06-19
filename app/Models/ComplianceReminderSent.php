<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasGarageScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $garage_id
 * @property string $vehicle_id
 * @property string $compliance_record_id
 * @property string $type
 * @property string $channel
 * @property string $recipient_type
 * @property string $recipient_id
 * @property int $window_days
 * @property Carbon $sent_at
 * @property string|null $error
 */
final class ComplianceReminderSent extends Model
{
    use HasFactory;
    use HasGarageScope;
    use HasUlids;

    /**
     * No timestamps columns — `sent_at` is the canonical timestamp; mutating
     * `error` via a follow-up update is the only non-create write path.
     */
    public $timestamps = false;

    protected $table = 'compliance_reminders_sent';

    protected $fillable = [
        'garage_id',
        'vehicle_id',
        'compliance_record_id',
        'type',
        'channel',
        'recipient_type',
        'recipient_id',
        'window_days',
        'sent_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'window_days' => 'integer',
        ];
    }
}
