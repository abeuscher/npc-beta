<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->string('source')->default('stripe_webhook')->after('status');
            $table->index('source');
        });

        DB::statement("UPDATE donations SET source = 'import' WHERE import_source_id IS NOT NULL");

        Schema::table('memberships', function (Blueprint $table) {
            $table->string('source')->default('human')->after('status');
            $table->index('source');
        });

        DB::statement("UPDATE memberships SET source = 'import' WHERE import_source_id IS NOT NULL");
        DB::statement("UPDATE memberships SET source = 'stripe_webhook' WHERE source <> 'import' AND (stripe_session_id IS NOT NULL OR stripe_subscription_id IS NOT NULL)");

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('source')->default('human')->after('status');
            $table->index('source');
        });

        DB::statement("UPDATE event_registrations SET source = 'import' WHERE import_session_id IS NOT NULL");
        DB::statement("UPDATE event_registrations SET source = 'stripe_webhook' WHERE source <> 'import' AND (stripe_session_id IS NOT NULL OR stripe_payment_intent_id IS NOT NULL)");

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('source')->default('human')->after('status');
            $table->index('source');
        });

        DB::statement("UPDATE transactions SET source = 'import' WHERE import_source_id IS NOT NULL");
        DB::statement("UPDATE transactions SET source = 'stripe_webhook' WHERE source <> 'import' AND stripe_id IS NOT NULL");
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
