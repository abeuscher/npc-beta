<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropForeign(['event_date_id']);
            $table->dropColumn('event_date_id');
            $table->foreignUuid('event_id')
                ->after('id')
                ->constrained('events')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropColumn('event_id');
            $table->foreignUuid('event_date_id')
                ->after('id')
                ->constrained('event_dates')
                ->cascadeOnDelete();
        });
    }
};
