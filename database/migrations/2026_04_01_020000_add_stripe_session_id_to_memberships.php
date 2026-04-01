<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->string('stripe_session_id')->nullable()->after('amount_paid');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn(['stripe_session_id', 'stripe_subscription_id']);
        });
    }
};
