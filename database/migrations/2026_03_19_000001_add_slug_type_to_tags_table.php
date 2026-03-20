<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->string('type')->default('contact')->after('color');
            $table->string('slug')->nullable()->unique()->after('name');
        });

        // Backfill slugs for existing tags
        DB::table('tags')->orderBy('id')->each(function ($tag) {
            $base    = Str::slug($tag->name);
            $slug    = $base;
            $counter = 1;

            while (DB::table('tags')->where('slug', $slug)->where('id', '!=', $tag->id)->exists()) {
                $slug = $base . '-' . $counter++;
            }

            DB::table('tags')->where('id', $tag->id)->update(['slug' => $slug]);
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });

        // Replace the global name unique index with a (name, type) composite unique
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['name', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['name', 'type']);
            $table->unique('name');
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'type']);
        });
    }
};
