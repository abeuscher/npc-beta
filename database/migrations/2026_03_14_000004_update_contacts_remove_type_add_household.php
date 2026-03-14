<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Contact is always a person. The 'type' and 'organization_name' fields
            // belonged to the old individual/organization contact type system.
            // Organizations are now a fully separate model. See ADR 014.
            $table->dropColumn(['type', 'organization_name']);

            // Household grouping — contacts optionally belong to a household.
            // When set, the household address is canonical for this contact.
            $table->foreignUuid('household_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('households')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['household_id']);
            $table->dropColumn('household_id');
            $table->string('type')->default('individual')->after('organization_id');
            $table->string('organization_name')->nullable()->after('type');
        });
    }
};
