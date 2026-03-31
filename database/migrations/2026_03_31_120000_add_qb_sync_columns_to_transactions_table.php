<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->text('qb_sync_error')->nullable()->after('quickbooks_id');
            $table->timestamp('qb_synced_at')->nullable()->after('qb_sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['qb_sync_error', 'qb_synced_at']);
        });
    }
};
