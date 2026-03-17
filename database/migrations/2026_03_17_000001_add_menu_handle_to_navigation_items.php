<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->string('menu_handle')->nullable()->default('primary')->after('is_visible');
        });

        DB::table('navigation_items')->whereNull('menu_handle')->update(['menu_handle' => 'primary']);
    }

    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropColumn('menu_handle');
        });
    }
};
