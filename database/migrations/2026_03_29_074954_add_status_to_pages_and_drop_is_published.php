<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('author_id');
        });

        // Backfill: is_published=true → 'published', else 'draft'
        DB::table('pages')->where('is_published', true)->update(['status' => 'published']);
        DB::table('pages')->where('is_published', false)->update(['status' => 'draft']);

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('is_published')->default(false)->after('author_id');
        });

        DB::table('pages')->where('status', 'published')->update(['is_published' => true]);
        DB::table('pages')->where('status', '!=', 'published')->update(['is_published' => false]);

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
