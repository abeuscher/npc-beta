<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type');              // 'page' or 'content'
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->jsonb('definition')->default('{}');
            $table->string('primary_color')->nullable();
            $table->string('heading_font')->nullable();
            $table->string('body_font')->nullable();
            $table->string('header_bg_color')->nullable();
            $table->string('footer_bg_color')->nullable();
            $table->string('nav_link_color')->nullable();
            $table->string('nav_hover_color')->nullable();
            $table->string('nav_active_color')->nullable();
            $table->text('custom_scss')->nullable();
            $table->uuid('header_page_id')->nullable();
            $table->uuid('footer_page_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('header_page_id')->references('id')->on('pages')->nullOnDelete();
            $table->foreign('footer_page_id')->references('id')->on('pages')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
