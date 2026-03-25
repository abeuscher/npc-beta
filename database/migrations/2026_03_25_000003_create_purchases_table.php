<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products');
            $table->uuid('product_price_id');
            $table->foreign('product_price_id')->references('id')->on('product_prices');
            $table->uuid('contact_id')->nullable();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->string('stripe_session_id')->nullable();
            $table->decimal('amount_paid', 10, 2);
            $table->string('status')->default('active');
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
