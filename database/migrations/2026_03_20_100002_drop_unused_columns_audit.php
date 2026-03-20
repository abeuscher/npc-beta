<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // preferred_name: not exposed in any UI; no current use case.
        // is_deceased: used only as a table filter; removing the column removes the filter too.
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['preferred_name', 'is_deceased']);
        });

        // organization_id on donations: not in the edit form; donation attribution
        // is contact-only for now. Drop the column and FK.
        Schema::table('donations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });

        // color on tags: not exposed in any UI.
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('preferred_name')->nullable()->after('last_name');
            $table->boolean('is_deceased')->default(false)->after('mailing_list_opt_in');
        });

        Schema::table('donations', function (Blueprint $table) {
            $table->foreignUuid('organization_id')->nullable()->after('contact_id')
                ->constrained('organizations')->nullOnDelete();
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->string('color')->nullable()->after('name');
        });
    }
};
