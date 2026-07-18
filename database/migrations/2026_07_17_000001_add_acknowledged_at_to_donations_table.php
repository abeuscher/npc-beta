<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            // Per-gift idempotency marker for the automatic tax-acknowledgment
            // email (session 373 / C3b). Null = not yet acknowledged; set to the
            // dispatch time once the acknowledgment is queued, so a webhook replay
            // never double-emails the donor. Distinct from the annual
            // donation_receipts table, which is keyed (contact_id, tax_year).
            $table->timestamp('acknowledged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn('acknowledged_at');
        });
    }
};
