<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_sources', function (Blueprint $table) {
            $table->jsonb('field_map')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('custom_field_map')->default(DB::raw("'{}'::jsonb"));
            $table->string('match_key')->nullable();
            $table->string('match_key_column')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('import_sources', function (Blueprint $table) {
            $table->dropColumn(['field_map', 'custom_field_map', 'match_key', 'match_key_column']);
        });
    }
};
