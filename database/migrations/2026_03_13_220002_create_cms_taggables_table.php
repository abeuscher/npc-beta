<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_taggables', function (Blueprint $table) {
            $table->foreignUuid('cms_tag_id')->constrained('cms_tags')->cascadeOnDelete();
            $table->string('taggable_type');
            $table->uuid('taggable_id');

            $table->index(['cms_tag_id', 'taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_taggables');
    }
};
