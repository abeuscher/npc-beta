<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contact_id');
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->string('tier');
            $table->string('status')->default('pending');
            $table->date('starts_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
