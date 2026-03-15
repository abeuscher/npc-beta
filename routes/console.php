<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Production: ensure 'php artisan schedule:run' is called every minute via cron.
Schedule::command('events:send-reminders')->dailyAt('08:00');
