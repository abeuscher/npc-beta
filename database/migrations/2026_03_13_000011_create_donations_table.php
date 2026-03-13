<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contact_id');
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->uuid('campaign_id')->nullable();
            $table->foreign('campaign_id')->references('id')->on('campaigns')->nullOnDelete();
            $table->uuid('fund_id')->nullable();
            $table->foreign('fund_id')->references('id')->on('funds')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('donated_on');
            $table->string('method')->default('other');
            $table->string('reference')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
