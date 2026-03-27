<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->uuid('contact_id')->nullable()->after('subject_id');
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
        });

        DB::statement("
            UPDATE transactions
            SET contact_id = (
                SELECT contact_id FROM donations WHERE donations.id::text = transactions.subject_id
            )
            WHERE subject_type = 'App\\\\Models\\\\Donation'
            AND subject_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropColumn('contact_id');
        });
    }
};
