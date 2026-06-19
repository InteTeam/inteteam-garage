<?php

declare(strict_types=1);

use App\Console\Commands\CheckJobTimeouts;
use App\Console\Commands\DispatchComplianceReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(CheckJobTimeouts::class)->hourly();
Schedule::command(DispatchComplianceReminders::class)->dailyAt('09:00');
