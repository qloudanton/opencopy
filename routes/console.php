<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process scheduled content for generation (runs hourly, spreads jobs over 55 minutes)
Schedule::command('content:process-scheduled --days=1 --limit=100 --spread=55')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Auto-publish scheduled content every minute
Schedule::command('content:publish-scheduled')->everyMinute();
