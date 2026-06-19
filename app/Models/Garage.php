<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\GaragePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property bool $online_payment_enabled
 * @property string $default_notification_channel
 * @property bool $staff_channel_toggle_default
 * @property string $timeout_reminder_policy
 * @property array<string, mixed>|null $working_hours
 * @property string $locale
 * @property bool $compliance_reminders_enabled
 * @property string|null $compliance_reminders_channel
 * @property array<int, int>|null $compliance_reminders_windows
 * @property string $compliance_reminders_recipient
 * @property array<int, string>|null $compliance_reminders_types
 */
#[UsePolicy(GaragePolicy::class)]
final class Garage extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_IN_APP = 'in_app';

    public const CHANNELS = [
        self::CHANNEL_EMAIL,
        self::CHANNEL_SMS,
        self::CHANNEL_IN_APP,
    ];

    public const TIMEOUT_POLICY_24_7 = '24_7';

    public const TIMEOUT_POLICY_WORKING_HOURS = 'working_hours';

    public const TIMEOUT_POLICY_ON_CALL = 'on_call';

    public const TIMEOUT_POLICIES = [
        self::TIMEOUT_POLICY_24_7,
        self::TIMEOUT_POLICY_WORKING_HOURS,
        self::TIMEOUT_POLICY_ON_CALL,
    ];

    /** Default windows (days before expiry) if garage enables reminders without picking any. */
    public const DEFAULT_REMINDER_WINDOWS = [30, 7];

    public const DEFAULT_REMINDER_TYPES = ['mot', 'tax', 'insurance'];

    protected $fillable = [
        'name',
        'slug',
        'online_payment_enabled',
        'default_notification_channel',
        'staff_channel_toggle_default',
        'timeout_reminder_policy',
        'working_hours',
        'locale',
        'compliance_reminders_enabled',
        'compliance_reminders_channel',
        'compliance_reminders_windows',
        'compliance_reminders_recipient',
        'compliance_reminders_types',
    ];

    protected function casts(): array
    {
        return [
            'online_payment_enabled' => 'boolean',
            'staff_channel_toggle_default' => 'boolean',
            'working_hours' => 'array',
            'compliance_reminders_enabled' => 'boolean',
            'compliance_reminders_windows' => 'array',
            'compliance_reminders_types' => 'array',
        ];
    }

    public function mechanics(): HasMany
    {
        return $this->hasMany(Mechanic::class);
    }

    public function repairJobs(): HasMany
    {
        return $this->hasMany(RepairJob::class);
    }

    public function onCallShifts(): HasMany
    {
        return $this->hasMany(MechanicOnCall::class);
    }

    public function isWithinWorkingHoursNow(): bool
    {
        if (! is_array($this->working_hours)) {
            return false;
        }

        $now = now();
        $dayKey = strtolower($now->format('D'));
        $entry = $this->working_hours[$dayKey] ?? null;

        if (! is_array($entry)) {
            return false;
        }

        $open = $entry['open'] ?? null;
        $close = $entry['close'] ?? null;

        if (! is_string($open) || ! is_string($close)) {
            return false;
        }

        $current = $now->format('H:i');

        return $current >= $open && $current <= $close;
    }
}
