<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widget_types', function (Blueprint $table) {
            $table->boolean('full_width')->default(false)->after('default_open');
        });
    }

    public function down(): void
    {
        Schema::table('widget_types', function (Blueprint $table) {
            $table->dropColumn('full_width');
        });
    }
};
