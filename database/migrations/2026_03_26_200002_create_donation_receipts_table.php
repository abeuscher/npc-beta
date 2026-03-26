<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('contact_id');
            $table->integer('tax_year');
            $table->timestamp('sent_at');
            $table->decimal('total_amount', 10, 2);
            $table->json('breakdown');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->index(['contact_id', 'tax_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_receipts');
    }
};
