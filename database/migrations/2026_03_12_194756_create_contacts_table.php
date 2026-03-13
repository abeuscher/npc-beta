<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['individual', 'organization'])->default('individual');
            $table->string('prefix')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('preferred_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('email_secondary')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_secondary')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable()->default('US');
            $table->text('notes')->nullable();
            $table->jsonb('custom_data')->nullable();
            $table->boolean('is_deceased')->default(false);
            $table->boolean('do_not_contact')->default(false);
            $table->string('source')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
