<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_detail_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('handle');
            $table->string('record_type');
            $table->string('label');
            $table->integer('sort_order')->default(0);
            $table->jsonb('layout_config')->nullable();
            $table->timestamps();

            $table->index('handle');
            $table->index('record_type');
            $table->unique(['record_type', 'handle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_detail_views');
    }
};
