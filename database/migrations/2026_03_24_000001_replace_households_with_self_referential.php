<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop FK from contacts.household_id → households.id first
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['household_id']);
        });

        // Drop households table (now safe)
        Schema::dropIfExists('households');

        // Add self-referential FK: contacts.household_id → contacts.id
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreign('household_id')
                ->references('id')
                ->on('contacts')
                ->nullOnDelete();
        });

        // Seed: make every existing contact their own household head
        DB::statement('UPDATE contacts SET household_id = id WHERE household_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['household_id']);
        });

        Schema::create('households', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('US');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
