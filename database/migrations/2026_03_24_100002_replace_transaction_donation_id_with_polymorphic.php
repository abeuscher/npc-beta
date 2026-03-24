<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['donation_id']);
            $table->dropColumn('donation_id');

            $table->string('subject_type')->nullable()->after('id');
            $table->string('subject_id')->nullable()->after('subject_type');

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropColumn(['subject_type', 'subject_id']);

            $table->uuid('donation_id')->nullable()->after('id');
            $table->foreign('donation_id')->references('id')->on('donations')->nullOnDelete();
        });
    }
};
