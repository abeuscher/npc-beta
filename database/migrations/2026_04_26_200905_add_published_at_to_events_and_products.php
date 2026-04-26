<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('status');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('status');
        });

        DB::statement("UPDATE events SET published_at = created_at WHERE status = 'published' AND published_at IS NULL");
        DB::statement("UPDATE products SET published_at = created_at WHERE status = 'published' AND is_archived = false AND published_at IS NULL");
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
