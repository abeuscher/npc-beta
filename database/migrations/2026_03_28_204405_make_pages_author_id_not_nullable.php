<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Assign any orphaned pages to the first user so the NOT NULL constraint can be applied.
        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId) {
            DB::table('pages')->whereNull('author_id')->update(['author_id' => $firstUserId]);
        }

        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
        });

        DB::statement('ALTER TABLE pages ALTER COLUMN author_id SET NOT NULL');

        Schema::table('pages', function (Blueprint $table) {
            $table->foreign('author_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
        });

        DB::statement('ALTER TABLE pages ALTER COLUMN author_id DROP NOT NULL');

        Schema::table('pages', function (Blueprint $table) {
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
