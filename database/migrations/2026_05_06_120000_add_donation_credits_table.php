<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_credits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('donation_id')
                ->constrained('donations')
                ->cascadeOnDelete();
            $table->string('attributable_type');
            $table->uuid('attributable_id');
            $table->decimal('credit_pct', 5, 2);
            $table->text('credit_role')->nullable();
            $table->timestamps();

            $table->index('donation_id');
            $table->index(['attributable_type', 'attributable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_credits');
    }
};
