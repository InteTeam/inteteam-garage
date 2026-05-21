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
 * @property string $locale
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

    protected $fillable = [
        'name',
        'slug',
        'online_payment_enabled',
        'default_notification_channel',
        'locale',
    ];

    protected function casts(): array
    {
        return [
            'online_payment_enabled' => 'boolean',
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
}
