<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_sources', function (Blueprint $table) {
            $table->renameColumn('field_map', 'contacts_field_map');
            $table->renameColumn('custom_field_map', 'contacts_custom_field_map');
            $table->renameColumn('match_key', 'contacts_match_key');
            $table->renameColumn('match_key_column', 'contacts_match_key_column');
        });
    }

    public function down(): void
    {
        Schema::table('import_sources', function (Blueprint $table) {
            $table->renameColumn('contacts_field_map', 'field_map');
            $table->renameColumn('contacts_custom_field_map', 'custom_field_map');
            $table->renameColumn('contacts_match_key', 'match_key');
            $table->renameColumn('contacts_match_key_column', 'match_key_column');
        });
    }
};
