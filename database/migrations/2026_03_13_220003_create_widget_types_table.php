<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('handle')->unique();
            $table->string('label');
            $table->enum('render_mode', ['server', 'client'])->default('server');
            $table->jsonb('collections')->default('[]');
            $table->text('template')->nullable();
            $table->text('css')->nullable();
            $table->text('js')->nullable();
            $table->string('variable_name')->nullable();
            $table->text('code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_types');
    }
};
