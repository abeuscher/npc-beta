<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('source')->default('human')->after('country');
            $table->index('source');

            $table->jsonb('custom_fields')->nullable()->after('source');

            $table->foreignUuid('import_source_id')
                ->nullable()
                ->after('custom_fields')
                ->constrained('import_sources')
                ->nullOnDelete();

            $table->foreignUuid('import_session_id')
                ->nullable()
                ->after('import_source_id')
                ->constrained('import_sessions')
                ->nullOnDelete();

            $table->string('external_id')->nullable()->after('import_session_id');

            $table->index(
                ['import_source_id', 'external_id'],
                'organizations_import_external_idx'
            );
        });

        Schema::table('import_sources', function (Blueprint $table) {
            $table->jsonb('organizations_field_map')->nullable()->after('notes_contact_match_key');
            $table->jsonb('organizations_custom_field_map')->nullable()->after('organizations_field_map');
            $table->string('organizations_match_key')->nullable()->after('organizations_custom_field_map');
        });
    }

    public function down(): void
    {
        Schema::table('import_sources', function (Blueprint $table) {
            $table->dropColumn([
                'organizations_field_map',
                'organizations_custom_field_map',
                'organizations_match_key',
            ]);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex('organizations_import_external_idx');
            $table->dropForeign(['import_session_id']);
            $table->dropForeign(['import_source_id']);
            $table->dropIndex(['source']);
            $table->dropColumn([
                'source',
                'custom_fields',
                'import_source_id',
                'import_session_id',
                'external_id',
            ]);
        });
    }
};
