<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_widgets', function (Blueprint $table) {
            $table->dropColumn('widget_type');
            $table->foreignUuid('widget_type_id')->after('page_id')->constrained('widget_types')->cascadeOnDelete();
            $table->jsonb('query_config')->default('{}')->after('config');
        });
    }

    public function down(): void
    {
        Schema::table('page_widgets', function (Blueprint $table) {
            $table->dropForeign(['widget_type_id']);
            $table->dropColumn(['widget_type_id', 'query_config']);
            $table->string('widget_type')->after('page_id');
        });
    }
};
