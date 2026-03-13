<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label');
            $table->string('url')->nullable();
            $table->uuid('page_id')->nullable();
            $table->foreign('page_id')->references('id')->on('pages')->nullOnDelete();
            $table->uuid('post_id')->nullable();
            $table->foreign('post_id')->references('id')->on('posts')->nullOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('target')->default('_self');
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        // Self-referential FK must be added after table creation so the PK exists
        Schema::table('navigation_items', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('navigation_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
