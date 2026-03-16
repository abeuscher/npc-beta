<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description');
            $table->longText('content');
            $table->json('tags')->nullable();
            $table->string('app_version')->nullable();
            $table->date('last_updated')->nullable();
            // Placeholder for future pgvector embedding (jsonb stores float array).
            $table->jsonb('embedding')->nullable();
            $table->timestamps();
        });

        Schema::create('help_article_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_article_id')->constrained()->cascadeOnDelete();
            $table->string('route_name');
            $table->unique(['help_article_id', 'route_name']);
            $table->index('route_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_article_routes');
        Schema::dropIfExists('help_articles');
    }
};
