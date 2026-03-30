<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('og_image_path')->nullable()->after('meta_description');
            $table->boolean('noindex')->default(false)->after('og_image_path');
            $table->text('head_snippet')->nullable()->after('noindex');
            $table->text('body_snippet')->nullable()->after('head_snippet');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['og_image_path', 'noindex', 'head_snippet', 'body_snippet']);
        });
    }
};
