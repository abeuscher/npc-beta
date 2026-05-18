<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The standard Laravel notifications table. Never created before this install
 * (nothing needed async notifications); session 303 turns on Filament's
 * persistent notification bell as the delivery surface for queued
 * export/import results (media-portability draft decision #5).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
