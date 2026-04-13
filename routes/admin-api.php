<?php

use App\Http\Controllers\Admin\PageBuilderApiController;
use App\Http\Controllers\Admin\PresetController;
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
*/

// Widget CRUD
Route::get('{page}/widgets', [PageBuilderApiController::class, 'index']);
Route::post('{page}/widgets', [PageBuilderApiController::class, 'store']);
Route::put('widgets/{widget}', [PageBuilderApiController::class, 'update']);
Route::delete('widgets/{widget}', [PageBuilderApiController::class, 'destroy']);
Route::post('widgets/{widget}/copy', [PageBuilderApiController::class, 'copy']);
Route::put('{page}/widgets/reorder', [PageBuilderApiController::class, 'reorder']);

// Layout CRUD
Route::post('{page}/layouts', [PageBuilderApiController::class, 'storeLayout']);
Route::put('layouts/{layout}', [PageBuilderApiController::class, 'updateLayout']);
Route::delete('layouts/{layout}', [PageBuilderApiController::class, 'destroyLayout']);

// Preview
Route::get('widgets/{widget}/preview', [PageBuilderApiController::class, 'preview']);

// Lookups
Route::get('widget-types', [PageBuilderApiController::class, 'widgetTypes']);
Route::get('collections', [PageBuilderApiController::class, 'collections']);
Route::get('collections/{handle}/fields', [PageBuilderApiController::class, 'collectionFields']);
Route::get('tags', [PageBuilderApiController::class, 'tags']);
Route::get('pages', [PageBuilderApiController::class, 'pages']);
Route::get('events', [PageBuilderApiController::class, 'events']);
Route::get('data-sources/{source}', [PageBuilderApiController::class, 'dataSources']);

// Image upload
Route::post('widgets/{widget}/image', [PageBuilderApiController::class, 'uploadImage']);
Route::delete('widgets/{widget}/image/{key}', [PageBuilderApiController::class, 'removeImage']);

// Appearance background image
Route::post('widgets/{widget}/appearance-image', [PageBuilderApiController::class, 'uploadAppearanceImage']);
Route::delete('widgets/{widget}/appearance-image', [PageBuilderApiController::class, 'removeAppearanceImage']);

// Color swatches
Route::put('color-swatches', [PageBuilderApiController::class, 'updateColorSwatches']);

// Widget presets (designer drafts)
Route::post('widget-presets', [PresetController::class, 'store']);
Route::patch('widget-presets/{preset}', [PresetController::class, 'update']);
Route::delete('widget-presets/{preset}', [PresetController::class, 'destroy']);
