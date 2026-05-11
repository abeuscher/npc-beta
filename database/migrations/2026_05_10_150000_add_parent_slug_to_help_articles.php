<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->string('parent_slug')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->dropColumn('parent_slug');
        });
    }
};
