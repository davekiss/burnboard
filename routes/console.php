<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync GitHub activity daily for all active users
Schedule::command('github:sync --period=week')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground();

// Also sync monthly stats weekly
Schedule::command('github:sync --period=month')
    ->weeklyOn(1, '05:00') // Monday at 5 AM
    ->withoutOverlapping()
    ->runInBackground();
