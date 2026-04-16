<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->foreignUuid('import_source_id')
                ->nullable()
                ->constrained('import_sources')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_source_id');
        });
    }
};
