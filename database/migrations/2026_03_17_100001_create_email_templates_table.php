<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('handle')->unique();
            $table->string('subject');
            $table->text('body');
            $table->string('header_color')->nullable();
            $table->string('header_image_path')->nullable();
            $table->string('header_text')->nullable();
            $table->string('footer_sender_name')->nullable();
            $table->string('footer_reply_to')->nullable();
            $table->text('footer_address')->nullable();
            $table->string('footer_reason')->nullable();
            $table->string('custom_template_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
