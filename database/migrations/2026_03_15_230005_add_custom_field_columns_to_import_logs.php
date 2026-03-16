<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            // Stores custom field definitions for columns mapped as "Create as custom field"
            // Format: { "Source Column Header": { handle, label, field_type } }
            $table->jsonb('custom_field_map')->nullable()->after('column_map');

            // Stores a log of what happened to each custom field def during this import
            // Format: [{ handle, label, action: 'created'|'reused' }]
            $table->jsonb('custom_field_log')->nullable()->after('custom_field_map');
        });
    }

    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropColumn(['custom_field_map', 'custom_field_log']);
        });
    }
};
