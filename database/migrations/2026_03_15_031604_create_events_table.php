<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'published', 'cancelled'])->default('draft');

            // Location — physical
            $table->boolean('is_in_person')->default(true);
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('map_url', 2048)->nullable();
            $table->string('map_label')->nullable();

            // Location — virtual
            $table->boolean('is_virtual')->default(false);
            $table->string('meeting_url', 2048)->nullable();

            // Registration settings
            $table->boolean('is_free')->default(true);
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('registration_open')->default(true);

            // Recurrence
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurrence_type', ['manual', 'rule'])->nullable();
            $table->json('recurrence_rule')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
