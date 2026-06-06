<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasGarageScope;
use App\Policies\RepairJobPolicy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $garage_id
 * @property string $vehicle_id
 * @property string $state
 * @property string|null $payment_reference
 * @property Carbon|null $payment_confirmed_at
 * @property-read Garage $garage
 * @property-read Vehicle $vehicle
 * @property-read Estimate|null $currentEstimate
 * @property-read HandoverInspection|null $handoverInspection
 * @property-read SignedPortalToken|null $portalToken
 * @property-read NotificationPreference|null $notificationPreference
 * @property-read Collection<int, JobStage> $stages
 * @property-read Collection<int, ApprovalEvent> $approvalEvents
 */
#[UsePolicy(RepairJobPolicy::class)]
final class RepairJob extends Model
{
    use HasFactory;
    use HasGarageScope;
    use HasUlids;
    use SoftDeletes;

    public const STATE_CREATED = 'created';

    public const STATE_BOOKED = 'booked';

    public const STATE_IN_PROGRESS = 'in_progress';

    public const STATE_AWAITING_APPROVAL = 'awaiting_approval';

    public const STATE_CUSTOMER_QUERY = 'customer_query';

    public const STATE_SCOPE_CHANGE = 'scope_change';

    public const STATE_APPROVED = 'approved';

    public const STATE_COMPLETED = 'completed';

    public const STATE_AWAITING_COLLECTION = 'awaiting_collection';

    public const STATE_COLLECTED = 'collected';

    public const STATES = [
        self::STATE_CREATED,
        self::STATE_BOOKED,
        self::STATE_IN_PROGRESS,
        self::STATE_AWAITING_APPROVAL,
        self::STATE_CUSTOMER_QUERY,
        self::STATE_SCOPE_CHANGE,
        self::STATE_APPROVED,
        self::STATE_COMPLETED,
        self::STATE_AWAITING_COLLECTION,
        self::STATE_COLLECTED,
    ];

    protected $fillable = [
        'garage_id',
        'vehicle_id',
        'payment_reference',
        'payment_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_confirmed_at' => 'datetime',
        ];
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function mechanics(): BelongsToMany
    {
        return $this->belongsToMany(Mechanic::class, 'repair_job_mechanic');
    }

    /**
     * @return HasMany<JobStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(JobStage::class, 'job_id')->orderBy('sort_order');
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class, 'job_id')->orderByDesc('revision_number');
    }

    public function currentEstimate(): HasOne
    {
        return $this->hasOne(Estimate::class, 'job_id')->latestOfMany('revision_number');
    }

    public function approvalEvents(): HasMany
    {
        return $this->hasMany(ApprovalEvent::class, 'job_id')->orderBy('occurred_at');
    }

    public function stateTransitions(): HasMany
    {
        return $this->hasMany(JobStateTransition::class, 'job_id')->orderBy('occurred_at');
    }

    public function portalToken(): HasOne
    {
        return $this->hasOne(SignedPortalToken::class, 'job_id')->latestOfMany('created_at');
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class, 'job_id');
    }

    public function handoverInspection(): HasOne
    {
        return $this->hasOne(HandoverInspection::class, 'job_id');
    }
}
