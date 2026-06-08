<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApprovalEvent;
use App\Models\Garage;
use App\Models\JobStateTransition;
use App\Models\Mechanic;
use App\Models\MechanicOnCall;
use App\Models\RepairJob;
use App\Services\ApprovalEventService;
use App\Services\CrmNotificationService;
use App\Services\CrmStaffNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class CheckJobTimeouts extends Command
{
    protected $signature = 'garage:check-timeouts
                            {--hours=24 : Hours before a job is considered timed out}';

    protected $description = 'Flag jobs stuck in awaiting_approval, customer_query, or awaiting_collection and send reminder notifications to customer and staff';

    private const TIMEOUT_STATES = [
        RepairJob::STATE_AWAITING_APPROVAL,
        RepairJob::STATE_CUSTOMER_QUERY,
        RepairJob::STATE_AWAITING_COLLECTION,
    ];

    public function __construct(
        private readonly CrmNotificationService $notifications,
        private readonly CrmStaffNotificationService $staffNotifications,
        private readonly ApprovalEventService $approvalEvents,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        $jobs = RepairJob::withoutGlobalScopes()
            ->with(['vehicle', 'notificationPreference', 'garage', 'mechanics.user'])
            ->whereIn('state', self::TIMEOUT_STATES)
            ->get()
            ->filter(fn (RepairJob $job) => $this->isTimedOut($job, $hours));

        $total = $jobs->count();
        $alerted = 0;
        $staffAlerted = 0;

        foreach ($jobs as $job) {
            try {
                $this->notifications->notifyTimeoutReminder($job);

                $staffCount = $this->dispatchStaffTimeout($job);

                $this->approvalEvents->recordBySystem($job, ApprovalEvent::EVENT_TIMEOUT_ALERT, [
                    'state' => $job->state,
                    'timeout_hours' => $hours,
                    'staff_recipients' => $staffCount,
                ]);

                $alerted++;
                $staffAlerted += $staffCount;
                $this->line("  Alerted job {$job->id} (state: {$job->state}, staff: {$staffCount})");
            } catch (\Throwable $e) {
                $this->error("  Failed job {$job->id}: {$e->getMessage()}");
            }
        }

        $this->info("Checked {$total} timed-out jobs, sent {$alerted} customer reminders, {$staffAlerted} staff alerts.");

        return Command::SUCCESS;
    }

    private function dispatchStaffTimeout(RepairJob $job): int
    {
        /** @var Garage $garage */
        $garage = $job->garage;

        if (! in_array($garage->timeout_reminder_policy, Garage::TIMEOUT_POLICIES, true)) {
            Log::warning('Unknown timeout_reminder_policy — falling back to broadcast', [
                'garage_id' => $garage->id,
                'policy' => $garage->timeout_reminder_policy,
            ]);
        }

        $shouldDispatch = match ($garage->timeout_reminder_policy) {
            Garage::TIMEOUT_POLICY_24_7 => true,
            Garage::TIMEOUT_POLICY_WORKING_HOURS => $garage->isWithinWorkingHoursNow(),
            Garage::TIMEOUT_POLICY_ON_CALL => true,
            default => true,
        };

        if (! $shouldDispatch) {
            return 0;
        }

        $recipients = $this->resolveTimeoutRecipients($job);

        foreach ($recipients as $mechanic) {
            $this->staffNotifications->notifyTimeoutReminderToMechanic($job, $mechanic);
        }

        return $recipients->count();
    }

    /**
     * @return Collection<int, Mechanic>
     */
    private function resolveTimeoutRecipients(RepairJob $job): Collection
    {
        /** @var Garage $garage */
        $garage = $job->garage;

        if ($garage->timeout_reminder_policy === Garage::TIMEOUT_POLICY_ON_CALL) {
            $shift = MechanicOnCall::withoutGlobalScopes()
                ->where('garage_id', $garage->id)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->with('mechanic')
                ->first();

            if ($shift?->mechanic !== null) {
                return collect([$shift->mechanic]);
            }
            // Fall through to broadcast — Poka-Yoke: never drop alerts when nobody is on call.
        }

        /** @var Collection<int, Mechanic> $mechanics */
        $mechanics = collect($job->mechanics->all());

        return $mechanics;
    }

    private function isTimedOut(RepairJob $job, int $hours): bool
    {
        $lastTransition = JobStateTransition::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('to_state', $job->state)
            ->latest('occurred_at')
            ->first();

        if ($lastTransition === null) {
            return false;
        }

        if ($lastTransition->occurred_at->gt(now()->subHours($hours))) {
            return false;
        }

        return ! ApprovalEvent::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('event_type', ApprovalEvent::EVENT_TIMEOUT_ALERT)
            ->where('occurred_at', '>', $lastTransition->occurred_at)
            ->exists();
    }
}
