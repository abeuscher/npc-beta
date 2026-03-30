<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('widget_types', function (Blueprint $table) {
            $table->jsonb('assets')->default('{}')->after('collections');
        });
    }

    public function down(): void
    {
        Schema::table('widget_types', function (Blueprint $table) {
            $table->dropColumn('assets');
        });
    }
};
