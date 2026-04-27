<?php

use App\Http\Controllers\Admin\RecordDetailViewBuilderApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API routes — record detail view builder
|--------------------------------------------------------------------------
|
| Serves the Vue page-builder Pinia store reused in `record_detail` mode.
| Each `RecordDetailView` owns a set of `page_widgets` (and optionally
| `page_layouts`) rendered in the record-detail sidebar slot of the bound
| record type's Filament edit page. Every endpoint is gated by
| `manage_record_detail_views`; widget- and layout-keyed endpoints
| additionally verify the row belongs to the view in the URL (IDOR guard).
|
*/

Route::prefix('views/{view}')->group(function () {
    Route::get('widgets', [RecordDetailViewBuilderApiController::class, 'index']);
    Route::post('widgets', [RecordDetailViewBuilderApiController::class, 'store']);
    Route::put('widgets/reorder', [RecordDetailViewBuilderApiController::class, 'reorder']);
    Route::put('widgets/{widget}', [RecordDetailViewBuilderApiController::class, 'update']);
    Route::delete('widgets/{widget}', [RecordDetailViewBuilderApiController::class, 'destroy']);
    Route::get('widgets/{widget}/preview', [RecordDetailViewBuilderApiController::class, 'preview']);
    Route::get('widget-types', [RecordDetailViewBuilderApiController::class, 'widgetTypes']);
    Route::post('widgets/{widget}/appearance-image', [RecordDetailViewBuilderApiController::class, 'uploadAppearanceImage']);
    Route::delete('widgets/{widget}/appearance-image', [RecordDetailViewBuilderApiController::class, 'removeAppearanceImage']);

    Route::post('layouts', [RecordDetailViewBuilderApiController::class, 'storeLayout']);
    Route::put('layouts/{layout}', [RecordDetailViewBuilderApiController::class, 'updateLayout']);
    Route::delete('layouts/{layout}', [RecordDetailViewBuilderApiController::class, 'destroyLayout']);
});
