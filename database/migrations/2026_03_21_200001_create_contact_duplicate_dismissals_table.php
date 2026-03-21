<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_duplicate_dismissals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contact_id_a')->constrained('contacts')->cascadeOnDelete();
            $table->foreignUuid('contact_id_b')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('dismissed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('dismissed_at')->useCurrent();

            $table->unique(['contact_id_a', 'contact_id_b']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_duplicate_dismissals');
    }
};
