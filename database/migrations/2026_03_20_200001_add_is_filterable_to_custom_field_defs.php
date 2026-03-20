<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_field_defs', function (Blueprint $table) {
            $table->boolean('is_filterable')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('custom_field_defs', function (Blueprint $table) {
            $table->dropColumn('is_filterable');
        });
    }
};
