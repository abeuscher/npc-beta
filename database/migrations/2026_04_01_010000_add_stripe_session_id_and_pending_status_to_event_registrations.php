<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('stripe_session_id')->nullable()->after('stripe_payment_intent_id');
        });

        // Expand the status check constraint to include 'pending'
        DB::statement("ALTER TABLE event_registrations DROP CONSTRAINT event_registrations_status_check");
        DB::statement("ALTER TABLE event_registrations ADD CONSTRAINT event_registrations_status_check CHECK (status::text = ANY (ARRAY['pending','registered','waitlisted','cancelled','attended']))");
    }

    public function down(): void
    {
        // Restore original check constraint
        DB::statement("ALTER TABLE event_registrations DROP CONSTRAINT event_registrations_status_check");
        DB::statement("ALTER TABLE event_registrations ADD CONSTRAINT event_registrations_status_check CHECK (status::text = ANY (ARRAY['registered','waitlisted','cancelled','attended']))");

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('stripe_session_id');
        });
    }
};
