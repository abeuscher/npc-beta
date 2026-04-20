<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('type')->default('note')->after('author_id');
            $table->string('subject')->nullable()->after('type');
            $table->string('status')->default('completed')->after('subject');
            $table->timestamp('follow_up_at')->nullable()->after('occurred_at');
            $table->text('outcome')->nullable()->after('follow_up_at');
            $table->integer('duration_minutes')->nullable()->after('outcome');
            $table->jsonb('meta')->nullable()->after('duration_minutes');

            $table->index('type', 'notes_type_index');
        });

        DB::statement('CREATE INDEX notes_notable_occurred_at_index ON notes (notable_type, notable_id, occurred_at DESC)');
        DB::statement('CREATE INDEX notes_follow_up_at_index ON notes (follow_up_at) WHERE follow_up_at IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS notes_follow_up_at_index');
        DB::statement('DROP INDEX IF EXISTS notes_notable_occurred_at_index');

        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex('notes_type_index');
            $table->dropColumn([
                'type',
                'subject',
                'status',
                'follow_up_at',
                'outcome',
                'duration_minutes',
                'meta',
            ]);
        });
    }
};
