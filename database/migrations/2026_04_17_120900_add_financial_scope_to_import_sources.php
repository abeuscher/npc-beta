<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_sources', function (Blueprint $table) {
            // Donations-scoped presets
            $table->jsonb('donations_field_map')->default('{}')->after('events_contact_match_key');
            $table->jsonb('donations_custom_field_map')->default('{}')->after('donations_field_map');
            $table->string('donations_contact_match_key')->nullable()->after('donations_custom_field_map');

            // Memberships-scoped presets
            $table->jsonb('memberships_field_map')->default('{}')->after('donations_contact_match_key');
            $table->jsonb('memberships_custom_field_map')->default('{}')->after('memberships_field_map');
            $table->string('memberships_contact_match_key')->nullable()->after('memberships_custom_field_map');

            // Invoice details-scoped presets
            $table->jsonb('invoices_field_map')->default('{}')->after('memberships_contact_match_key');
            $table->jsonb('invoices_custom_field_map')->default('{}')->after('invoices_field_map');
            $table->string('invoices_contact_match_key')->nullable()->after('invoices_custom_field_map');
        });
    }

    public function down(): void
    {
        Schema::table('import_sources', function (Blueprint $table) {
            $table->dropColumn([
                'donations_field_map', 'donations_custom_field_map', 'donations_contact_match_key',
                'memberships_field_map', 'memberships_custom_field_map', 'memberships_contact_match_key',
                'invoices_field_map', 'invoices_custom_field_map', 'invoices_contact_match_key',
            ]);
        });
    }
};
