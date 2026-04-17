<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->foreignUuid('import_source_id')->nullable()->after('notes')
                ->constrained('import_sources')->nullOnDelete();
            $table->foreignUuid('import_session_id')->nullable()->after('import_source_id')
                ->constrained('import_sessions')->nullOnDelete();
            $table->string('external_id')->nullable()->after('import_session_id');

            $table->index(['import_source_id', 'external_id'], 'memberships_import_external_idx');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex('memberships_import_external_idx');
            $table->dropForeign(['import_source_id']);
            $table->dropForeign(['import_session_id']);
            $table->dropColumn(['import_source_id', 'import_session_id', 'external_id']);
        });
    }
};
