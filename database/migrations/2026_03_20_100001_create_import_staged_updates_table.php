<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_staged_updates', function (Blueprint $table) {
            $table->id();
            $table->uuid('import_session_id');
            $table->uuid('contact_id');
            $table->jsonb('attributes')->nullable();
            $table->jsonb('tag_ids')->nullable();
            $table->timestamps();

            $table->foreign('import_session_id')
                ->references('id')
                ->on('import_sessions')
                ->cascadeOnDelete();

            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->cascadeOnDelete();

            $table->index('import_session_id');
            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_staged_updates');
    }
};
