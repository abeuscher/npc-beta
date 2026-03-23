<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->uuid('tier_id')->nullable()->after('contact_id');
            $table->foreign('tier_id')->references('id')->on('membership_tiers')->onDelete('set null');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->string('tier')->nullable();
            $table->dropForeign(['tier_id']);
            $table->dropColumn('tier_id');
        });
    }
};
