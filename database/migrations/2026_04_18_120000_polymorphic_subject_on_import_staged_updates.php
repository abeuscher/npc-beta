<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_staged_updates', function (Blueprint $table) {
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();
        });

        DB::statement("UPDATE import_staged_updates SET subject_type = 'App\\\\Models\\\\Contact', subject_id = contact_id WHERE contact_id IS NOT NULL");

        Schema::table('import_staged_updates', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropIndex('import_staged_updates_contact_id_index');
            $table->dropColumn('contact_id');
        });

        DB::statement('ALTER TABLE import_staged_updates ALTER COLUMN subject_type SET NOT NULL');
        DB::statement('ALTER TABLE import_staged_updates ALTER COLUMN subject_id SET NOT NULL');

        Schema::table('import_staged_updates', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('import_staged_updates', function (Blueprint $table) {
            $table->uuid('contact_id')->nullable();
        });

        DB::statement("UPDATE import_staged_updates SET contact_id = subject_id WHERE subject_type = 'App\\\\Models\\\\Contact'");

        DB::statement("DELETE FROM import_staged_updates WHERE subject_type <> 'App\\\\Models\\\\Contact'");

        DB::statement('ALTER TABLE import_staged_updates ALTER COLUMN contact_id SET NOT NULL');

        Schema::table('import_staged_updates', function (Blueprint $table) {
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->index('contact_id');
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropColumn(['subject_type', 'subject_id']);
        });
    }
};
