<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ComplianceReminderService;
use Illuminate\Console\Command;

final class DispatchComplianceReminders extends Command
{
    protected $signature = 'compliance:dispatch-reminders';

    protected $description = 'Send compliance expiry reminders (MOT / Tax / Insurance) for every garage that opted in';

    public function __construct(
        private readonly ComplianceReminderService $reminderService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $metrics = $this->reminderService->dispatchDue();

        $totalSent = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($metrics as $garageId => $row) {
            $this->line("  Garage {$garageId}: sent={$row['sent']} skipped={$row['skipped']} errors={$row['errors']}");
            $totalSent += $row['sent'];
            $totalSkipped += $row['skipped'];
            $totalErrors += $row['errors'];
        }

        $this->info("Compliance reminders: sent={$totalSent} skipped={$totalSkipped} errors={$totalErrors} across " . count($metrics) . ' garage(s).');

        return $totalErrors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
