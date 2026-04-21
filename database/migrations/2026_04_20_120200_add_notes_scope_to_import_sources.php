<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_sources', function (Blueprint $table) {
            $table->jsonb('notes_field_map')->default('{}')->after('invoices_contact_match_key');
            $table->jsonb('notes_custom_field_map')->default('{}')->after('notes_field_map');
            $table->string('notes_contact_match_key')->nullable()->after('notes_custom_field_map');
        });
    }

    public function down(): void
    {
        Schema::table('import_sources', function (Blueprint $table) {
            $table->dropColumn([
                'notes_field_map',
                'notes_custom_field_map',
                'notes_contact_match_key',
            ]);
        });
    }
};
