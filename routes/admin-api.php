<?php

use App\Http\Controllers\Admin\PageBuilderApiController;
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
