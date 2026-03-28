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
            $table->unsignedBigInteger('author_id')->nullable()->after('slug');
        });

        // Assign existing events to the first user before adding NOT NULL constraint.
        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId) {
            DB::table('events')->whereNull('author_id')->update(['author_id' => $firstUserId]);
        }

        DB::statement('ALTER TABLE events ALTER COLUMN author_id SET NOT NULL');

        Schema::table('events', function (Blueprint $table) {
            $table->foreign('author_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropColumn('author_id');
        });
    }
};
