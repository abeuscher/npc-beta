<?php

use App\Http\Controllers\Api\Fleet\BackupController;
use App\Http\Controllers\Api\Fleet\HealthController;
use App\Http\Controllers\Api\Fleet\LogsController;
use App\Http\Controllers\Api\Fleet\RecoveryController;
use App\Http\Middleware\VerifyFleetAgent;
use Illuminate\Support\Facades\Route;

// The five Fleet Manager agent endpoints. Auth is two independent locks:
// nginx mTLS at the TLS layer (docker/nginx/*.conf) and — as of contract
// v2.7.0 (Security S2) — the app-layer VerifyFleetAgent gate wrapping the group
// (a per-install shared-secret header, inert until a secret is provisioned).
// Per-route throttle buckets are preserved inside the group.
Route::middleware([VerifyFleetAgent::class])->group(function () {
    Route::middleware(['throttle:60,1'])
        ->get('/health', [HealthController::class, 'index']);

    Route::middleware(['throttle:60,1'])
        ->get('/logs', [LogsController::class, 'index']);

    Route::middleware(['throttle:6,1'])
        ->post('/backup/trigger', [BackupController::class, 'trigger']);

    Route::middleware(['throttle:60,1'])
        ->get('/backup/blob', [BackupController::class, 'blob']);

    Route::middleware(['throttle:6,1'])
        ->post('/admin/recover', [RecoveryController::class, 'recover']);
});
