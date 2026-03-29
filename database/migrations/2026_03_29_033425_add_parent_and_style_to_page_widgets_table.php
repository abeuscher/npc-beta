<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_widgets', function (Blueprint $table) {
            $table->uuid('parent_widget_id')->nullable()->after('page_id');
            $table->smallInteger('column_index')->unsigned()->nullable()->default(null)->after('parent_widget_id');
            $table->jsonb('style_config')->default('{}')->after('query_config');

            $table->foreign('parent_widget_id')
                  ->references('id')
                  ->on('page_widgets')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('page_widgets', function (Blueprint $table) {
            $table->dropForeign(['parent_widget_id']);
            $table->dropColumn(['parent_widget_id', 'column_index', 'style_config']);
        });
    }
};
