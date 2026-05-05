<?php

use App\Http\Controllers\Api\Fleet\BackupController;
use App\Http\Controllers\Api\Fleet\HealthController;
use App\Http\Controllers\Api\Fleet\LogsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1'])
    ->get('/health', [HealthController::class, 'index']);

Route::middleware(['throttle:60,1'])
    ->get('/logs', [LogsController::class, 'index']);

Route::middleware(['throttle:6,1'])
    ->post('/backup/trigger', [BackupController::class, 'trigger']);
