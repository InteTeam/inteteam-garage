<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApprovalEvent;
use App\Models\JobStateTransition;
use App\Models\RepairJob;
use App\Services\ApprovalEventService;
use App\Services\CrmNotificationService;
use Illuminate\Console\Command;

final class CheckJobTimeouts extends Command
{
    protected $signature = 'garage:check-timeouts
                            {--hours=24 : Hours before a job is considered timed out}';

    protected $description = 'Flag jobs stuck in awaiting_approval, customer_query, or awaiting_collection and send reminder notifications';

    private const TIMEOUT_STATES = [
        RepairJob::STATE_AWAITING_APPROVAL,
        RepairJob::STATE_CUSTOMER_QUERY,
        RepairJob::STATE_AWAITING_COLLECTION,
    ];

    public function __construct(
        private readonly CrmNotificationService $notifications,
        private readonly ApprovalEventService $approvalEvents,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        $jobs = RepairJob::withoutGlobalScopes()
            ->with(['vehicle', 'notificationPreference', 'garage'])
            ->whereIn('state', self::TIMEOUT_STATES)
            ->get()
            ->filter(fn (RepairJob $job) => $this->isTimedOut($job, $hours));

        $total = $jobs->count();
        $alerted = 0;

        foreach ($jobs as $job) {
            try {
                $this->notifications->notifyTimeoutReminder($job);
                $this->approvalEvents->recordBySystem($job, ApprovalEvent::EVENT_TIMEOUT_ALERT, [
                    'state' => $job->state,
                    'timeout_hours' => $hours,
                ]);
                $alerted++;
                $this->line("  Alerted job {$job->id} (state: {$job->state})");
            } catch (\Throwable $e) {
                $this->error("  Failed job {$job->id}: {$e->getMessage()}");
            }
        }

        $this->info("Checked {$total} timed-out jobs, sent {$alerted} reminders.");

        return Command::SUCCESS;
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
