<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_widgets', function (Blueprint $table) {
            $table->renameColumn('style_config', 'appearance_config');
        });
    }

    public function down(): void
    {
        // One-way migration — beta system, no live data, no rollback.
    }
};
