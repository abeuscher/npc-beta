<?php

use App\Http\Controllers\Admin\DashboardBuilderApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API routes — dashboard builder
|--------------------------------------------------------------------------
|
| Serves the Vue page-builder Pinia store reused in `dashboard` mode. Each
| `DashboardConfig` owns a set of root `page_widgets` rendered in the admin
| dashboard grid. Every endpoint is gated by `manage_dashboard_config`;
| widget-keyed endpoints additionally verify the widget belongs to the
| config in the URL (IDOR guard).
|
*/

Route::prefix('configs/{dashboardConfig}')->group(function () {
    Route::get('widgets', [DashboardBuilderApiController::class, 'index']);
    Route::post('widgets', [DashboardBuilderApiController::class, 'store']);
    Route::put('widgets/reorder', [DashboardBuilderApiController::class, 'reorder']);
    Route::put('widgets/{widget}', [DashboardBuilderApiController::class, 'update']);
    Route::delete('widgets/{widget}', [DashboardBuilderApiController::class, 'destroy']);
    Route::get('widgets/{widget}/preview', [DashboardBuilderApiController::class, 'preview']);
    Route::get('widget-types', [DashboardBuilderApiController::class, 'widgetTypes']);
    Route::post('widgets/{widget}/appearance-image', [DashboardBuilderApiController::class, 'uploadAppearanceImage']);
    Route::delete('widgets/{widget}/appearance-image', [DashboardBuilderApiController::class, 'removeAppearanceImage']);
});
