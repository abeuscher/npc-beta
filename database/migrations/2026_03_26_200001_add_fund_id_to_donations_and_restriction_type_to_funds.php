<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funds', function (Blueprint $table) {
            $table->string('restriction_type')->default('unrestricted')->after('is_active');
        });

        Schema::table('donations', function (Blueprint $table) {
            $table->uuid('fund_id')->nullable()->after('contact_id');
            $table->foreign('fund_id')->references('id')->on('funds')->nullOnDelete();
            $table->index('fund_id');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropForeign(['fund_id']);
            $table->dropIndex('donations_fund_id_index');
            $table->dropColumn('fund_id');
        });

        Schema::table('funds', function (Blueprint $table) {
            $table->dropColumn('restriction_type');
        });
    }
};
