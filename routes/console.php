<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Production: ensure 'php artisan schedule:run' is called every minute via cron.
// (The events reminder schedule is registered by the Events plugin provider.)
