<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_widgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('widget_type');
            $table->string('label')->nullable();
            $table->jsonb('config')->default('{}');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_widgets');
    }
};
