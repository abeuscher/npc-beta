<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('ticket_type')->nullable();
            $table->decimal('ticket_fee', 10, 2)->nullable();
            $table->string('payment_state')->nullable();

            $table->foreignUuid('transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->nullOnDelete();

            $table->foreignUuid('import_session_id')
                ->nullable()
                ->constrained('import_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_id');
            $table->dropConstrainedForeignId('import_session_id');
            $table->dropColumn(['ticket_type', 'ticket_fee', 'payment_state']);
        });
    }
};
