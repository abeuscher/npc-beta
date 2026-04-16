<?php

use App\Http\Controllers\Admin\PageBuilderApiController;
use App\Http\Controllers\Admin\PresetController;
use App\Http\Controllers\Admin\WidgetDefaultsController;
use App\Http\Middleware\ResolvePageBuilderOwner;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API routes — page builder
|--------------------------------------------------------------------------
|
| These routes serve the Vue page builder's REST API. They use Filament's
| admin panel middleware (session auth + CSRF) and are included from
| routes/web.php within the Filament middleware group.
|
| Widget stacks are polymorphic (owned by a Page or a Template). Owner-scoped
| routes live under /pages/{owner}/... and /templates/{owner}/... — the
| ResolvePageBuilderOwner middleware turns the {owner} UUID into the correct
| model before the controller runs.
|
*/

$ownerRoutes = function () {
    Route::get('widgets', [PageBuilderApiController::class, 'index']);
    Route::post('widgets', [PageBuilderApiController::class, 'store']);
    Route::put('widgets/reorder', [PageBuilderApiController::class, 'reorder']);
    Route::post('layouts', [PageBuilderApiController::class, 'storeLayout']);
};

Route::prefix('pages/{owner}')
    ->middleware(ResolvePageBuilderOwner::class . ':page')
    ->group($ownerRoutes);

Route::prefix('templates/{owner}')
    ->middleware(ResolvePageBuilderOwner::class . ':template')
    ->group($ownerRoutes);

// Widget-keyed routes (owner inferred via $widget->owner).
Route::put('widgets/{widget}', [PageBuilderApiController::class, 'update']);
Route::delete('widgets/{widget}', [PageBuilderApiController::class, 'destroy']);
Route::post('widgets/{widget}/copy', [PageBuilderApiController::class, 'copy']);

// Layout-keyed routes.
Route::put('layouts/{layout}', [PageBuilderApiController::class, 'updateLayout']);
Route::delete('layouts/{layout}', [PageBuilderApiController::class, 'destroyLayout']);

// Preview.
Route::get('widgets/{widget}/preview', [PageBuilderApiController::class, 'preview']);

// Lookups.
Route::get('widget-types', [PageBuilderApiController::class, 'widgetTypes']);
Route::get('collections', [PageBuilderApiController::class, 'collections']);
Route::get('collections/{handle}/fields', [PageBuilderApiController::class, 'collectionFields']);
Route::get('tags', [PageBuilderApiController::class, 'tags']);
Route::get('pages', [PageBuilderApiController::class, 'pages']);
Route::get('events', [PageBuilderApiController::class, 'events']);
Route::get('data-sources/{source}', [PageBuilderApiController::class, 'dataSources']);

// Image upload.
Route::post('widgets/{widget}/image', [PageBuilderApiController::class, 'uploadImage']);
Route::delete('widgets/{widget}/image/{key}', [PageBuilderApiController::class, 'removeImage']);

// Appearance background image.
Route::post('widgets/{widget}/appearance-image', [PageBuilderApiController::class, 'uploadAppearanceImage']);
Route::delete('widgets/{widget}/appearance-image', [PageBuilderApiController::class, 'removeAppearanceImage']);

// Color swatches.
Route::put('color-swatches', [PageBuilderApiController::class, 'updateColorSwatches']);

// Widget presets (designer drafts).
Route::post('widget-presets', [PresetController::class, 'store']);
Route::patch('widget-presets/{preset}', [PresetController::class, 'update']);
Route::delete('widget-presets/{preset}', [PresetController::class, 'destroy']);

// Widget defaults export (designer copy-out to Definition files).
Route::post('widget-defaults/export', [WidgetDefaultsController::class, 'export']);
