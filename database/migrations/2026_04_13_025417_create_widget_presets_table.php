<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_presets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('widget_type_id');
            $table->string('handle');
            $table->string('label');
            $table->text('description')->nullable();
            $table->jsonb('config')->default('{}');
            $table->jsonb('appearance_config')->default('{}');
            $table->timestamps();

            $table->foreign('widget_type_id')
                ->references('id')->on('widget_types')
                ->cascadeOnDelete();

            $table->unique(['widget_type_id', 'handle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_presets');
    }
};
