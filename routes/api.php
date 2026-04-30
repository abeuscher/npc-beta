<?php

use App\Http\Controllers\Api\Fleet\HealthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1'])
    ->get('/health', [HealthController::class, 'index']);
