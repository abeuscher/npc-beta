<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill any rows that somehow have a null starts_at before removing nullable.
        DB::table('events')->whereNull('starts_at')->update(['starts_at' => now()]);

        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->change();
        });
    }
};
