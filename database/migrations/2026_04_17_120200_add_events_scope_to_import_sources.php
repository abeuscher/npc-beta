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
            $table->jsonb('events_field_map')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('events_custom_field_map')->default(DB::raw("'{}'::jsonb"));
            $table->string('events_match_key')->nullable();
            $table->string('events_match_key_column')->nullable();
            $table->string('events_contact_match_key')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('import_sources', function (Blueprint $table) {
            $table->dropColumn([
                'events_field_map',
                'events_custom_field_map',
                'events_match_key',
                'events_match_key_column',
                'events_contact_match_key',
            ]);
        });
    }
};
