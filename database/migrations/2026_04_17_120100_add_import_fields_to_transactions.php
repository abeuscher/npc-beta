<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUuid('import_source_id')
                ->nullable()
                ->constrained('import_sources')
                ->nullOnDelete();

            $table->foreignUuid('import_session_id')
                ->nullable()
                ->constrained('import_sessions')
                ->nullOnDelete();

            $table->string('external_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_channel')->nullable();

            $table->index(['import_source_id', 'external_id'], 'transactions_import_external_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_import_external_idx');
            $table->dropConstrainedForeignId('import_source_id');
            $table->dropConstrainedForeignId('import_session_id');
            $table->dropColumn(['external_id', 'payment_method', 'payment_channel']);
        });
    }
};
