<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->jsonb('custom_fields')->nullable()->after('external_id');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->jsonb('custom_fields')->nullable()->after('external_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->jsonb('custom_fields')->nullable()->after('line_items');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });
    }
};
