<?php

use App\Models\CollectionItem;
use App\Models\PageWidget;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        PageWidget::query()->chunkById(200, function ($widgets) {
            foreach ($widgets as $widget) {
                $widget->save();
            }
        });

        CollectionItem::query()->chunkById(200, function ($items) {
            foreach ($items as $item) {
                $item->save();
            }
        });
    }

    public function down(): void
    {
        // No reverse path: pre-beta license; the encoded shape was a regression bug.
    }
};
