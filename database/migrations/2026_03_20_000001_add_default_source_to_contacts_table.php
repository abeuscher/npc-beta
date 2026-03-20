<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill nulls before adding the default constraint
        DB::table('contacts')->whereNull('source')->update(['source' => 'manual']);

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('source')->default('manual')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('source')->default(null)->nullable()->change();
        });
    }
};
