<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Scheduled backups (configurable frequency)
$backupFrequency = config('backup.schedule.frequency', 'daily');
$backupTime = config('backup.schedule.time', '02:00');

if (config('backup.schedule.enabled')) {
    $backup = Schedule::command('backup:run');

    match ($backupFrequency) {
        'hourly' => $backup->hourly(),
        'daily' => $backup->dailyAt($backupTime),
        'weekly' => $backup->weeklyOn(
            config('backup.schedule.day_of_week', 0),
            $backupTime
        ),
        'monthly' => $backup->monthlyOn(
            config('backup.schedule.day_of_month', 1),
            $backupTime
        ),
        default => $backup->dailyAt($backupTime),
    };
}

// Queue worker monitoring
Schedule::command('queue:monitor database')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Suspicious activity check (failed logins, bulk exports)
Schedule::command('log:check-suspicious')
    ->everyFifteenMinutes()
    ->withoutOverlapping(15);

// Storage usage alert (when enabled in settings)
Schedule::command('storage:check-alerts')
    ->daily()
    ->withoutOverlapping(60);

// Integration usage budget alerts
Schedule::command('usage:check-budgets')
    ->daily()
    ->withoutOverlapping(60);

// Prune expired API keys and auto-revoke rotated keys past grace period
Schedule::command('api-keys:prune-expired')
    ->daily()
    ->withoutOverlapping(60);

// Scheduled price checks for shopping list items
Schedule::command('prices:check')
    ->hourly()
    ->withoutOverlapping(30);

// Scheduled store crawling — grocery/general (every 6 hours)
Schedule::command('prices:crawl-stores --category=grocery')
    ->everySixHours()
    ->withoutOverlapping(60);

Schedule::command('prices:crawl-stores --category=general')
    ->everySixHours()
    ->withoutOverlapping(60);

// Scheduled store crawling — other categories (twice daily, staggered)
Schedule::command('prices:crawl-stores --category=electronics')
    ->twiceDaily(3, 15)
    ->withoutOverlapping(60);

Schedule::command('prices:crawl-stores --category=home-improvement')
    ->twiceDaily(4, 16)
    ->withoutOverlapping(60);

Schedule::command('prices:crawl-stores --category=warehouse')
    ->twiceDaily(5, 17)
    ->withoutOverlapping(60);

Schedule::command('prices:crawl-stores --category=pharmacy')
    ->twiceDaily(6, 18)
    ->withoutOverlapping(60);

Schedule::command('prices:crawl-stores --category=delivery')
    ->twiceDaily(7, 19)
    ->withoutOverlapping(60);

// Expire scanned deals past their valid_to date
Schedule::call(function () {
    \App\Models\ScannedDeal::where('status', 'active')
        ->whereNotNull('valid_to')
        ->where('valid_to', '<', now()->toDateString())
        ->update(['status' => 'expired']);
})->name('deals:expire')->daily()->withoutOverlapping(60);
