<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widget_types', function (Blueprint $table) {
            $table->string('category')->default('content')->after('handle');
            $table->jsonb('allowed_page_types')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('widget_types', function (Blueprint $table) {
            $table->dropColumn(['category', 'allowed_page_types']);
        });
    }
};
