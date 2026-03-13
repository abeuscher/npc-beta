<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('donation_id')->nullable();
            $table->foreign('donation_id')->references('id')->on('donations')->nullOnDelete();
            $table->string('type')->default('donation');
            $table->decimal('amount', 10, 2);
            $table->string('direction')->default('in');
            $table->string('status')->default('pending');
            $table->string('stripe_id')->nullable();
            $table->string('quickbooks_id')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
