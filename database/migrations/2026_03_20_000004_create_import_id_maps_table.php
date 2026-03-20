<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_id_maps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('import_source_id')->constrained('import_sources')->cascadeOnDelete();
            $table->string('model_type');
            $table->string('source_id');
            $table->uuid('model_uuid');
            $table->timestamps();

            $table->unique(['import_source_id', 'model_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_id_maps');
    }
};
