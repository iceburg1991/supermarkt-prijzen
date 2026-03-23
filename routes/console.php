<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule scrape:all command to run daily
Schedule::command('scrape:all')
    ->dailyAt(config('scrapers.schedule.time', '02:00'))
    ->withoutOverlapping()
    ->onFailure(function () {
        // Send notification on failure
        Log::error('Scheduled scrape:all failed');
    })
    ->onSuccess(function () {
        Log::info('Scheduled scrape:all completed successfully');
    });
