<?php

use App\Jobs\DailyPriceCheck;
use App\Jobs\AI\FirecrawlDiscoveryJob;
use App\Jobs\AI\FirecrawlRefreshJob;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

// Run the daily price check scheduler every minute
// It will dispatch jobs for users whose configured time matches the current minute
Schedule::call(function () {
    DailyPriceCheck::scheduleForUsers();
})->everyMinute()->name('daily-price-check-scheduler')->withoutOverlapping();

// Alternative: Run a full check at 3:00 AM for all users who haven't set a custom time
Schedule::job(new DailyPriceCheck())->dailyAt('03:00')->name('daily-price-check-fallback');

/*
|--------------------------------------------------------------------------
| Firecrawl Web Crawler Schedules
|--------------------------------------------------------------------------
|
| These schedules handle Firecrawl-powered price updates:
| - Daily: Refresh prices for known product URLs at user's configured time
| - Weekly: Discover new sites carrying tracked products
|
*/

// Run Firecrawl daily refresh scheduler every minute
// It will dispatch jobs for users whose configured time matches the current minute
Schedule::call(function () {
    FirecrawlRefreshJob::scheduleForUsers();
})->everyMinute()->name('firecrawl-daily-refresh-scheduler')->withoutOverlapping();

// Weekly Firecrawl discovery - find new sites carrying tracked products
// Runs every Sunday at 4:00 AM to discover new vendors
Schedule::call(function () {
    FirecrawlDiscoveryJob::scheduleWeeklyDiscovery();
})->weekly()->sundays()->at('04:00')->name('firecrawl-weekly-discovery');
