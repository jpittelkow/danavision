<?php

use App\Jobs\DailyPriceCheck;
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
