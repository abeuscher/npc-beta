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
        // Copy cms_tags rows into tags with type='collection'
        $cmsTags = DB::table('cms_tags')->get();

        foreach ($cmsTags as $cmsTag) {
            // Generate a unique slug (the cms_tag already has one, but it may clash with existing tag slugs)
            $base    = $cmsTag->slug ?: Str::slug($cmsTag->name);
            $slug    = $base;
            $counter = 1;

            while (DB::table('tags')->where('slug', $slug)->exists()) {
                $slug = $base . '-' . $counter++;
            }

            DB::table('tags')->insert([
                'id'         => $cmsTag->id,
                'name'       => $cmsTag->name,
                'slug'       => $slug,
                'type'       => 'collection',
                'color'      => null,
                'created_at' => $cmsTag->created_at,
                'updated_at' => $cmsTag->updated_at,
            ]);
        }

        // Copy cms_taggables rows into taggables, mapping cms_tag_id → tag_id
        DB::table('cms_taggables')->orderBy('cms_tag_id')->each(function ($row) {
            // Only insert if the tag_id exists (it should, since we just inserted it)
            if (DB::table('tags')->where('id', $row->cms_tag_id)->exists()) {
                // Avoid duplicate pivot rows
                $exists = DB::table('taggables')
                    ->where('tag_id', $row->cms_tag_id)
                    ->where('taggable_type', $row->taggable_type)
                    ->where('taggable_id', $row->taggable_id)
                    ->exists();

                if (! $exists) {
                    DB::table('taggables')->insert([
                        'tag_id'        => $row->cms_tag_id,
                        'taggable_type' => $row->taggable_type,
                        'taggable_id'   => $row->taggable_id,
                    ]);
                }
            }
        });

        // Drop old tables
        Schema::dropIfExists('cms_taggables');
        Schema::dropIfExists('cms_tags');
    }

    public function down(): void
    {
        // Recreate cms_tags
        Schema::create('cms_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Recreate cms_taggables
        Schema::create('cms_taggables', function (Blueprint $table) {
            $table->foreignUuid('cms_tag_id')->constrained('cms_tags')->cascadeOnDelete();
            $table->string('taggable_type');
            $table->uuid('taggable_id');
            $table->index(['cms_tag_id', 'taggable_type', 'taggable_id']);
        });

        // Move collection-type tags back to cms_tags
        $collectionTags = DB::table('tags')->where('type', 'collection')->get();

        foreach ($collectionTags as $tag) {
            DB::table('cms_tags')->insert([
                'id'         => $tag->id,
                'name'       => $tag->name,
                'slug'       => $tag->slug,
                'created_at' => $tag->created_at,
                'updated_at' => $tag->updated_at,
            ]);
        }

        // Move taggables rows back to cms_taggables for collection tags
        $collectionTagIds = DB::table('tags')->where('type', 'collection')->pluck('id');

        DB::table('taggables')
            ->whereIn('tag_id', $collectionTagIds)
            ->each(function ($row) {
                DB::table('cms_taggables')->insert([
                    'cms_tag_id'    => $row->tag_id,
                    'taggable_type' => $row->taggable_type,
                    'taggable_id'   => $row->taggable_id,
                ]);
            });

        // Remove collection tags from tags table
        DB::table('tags')->where('type', 'collection')->delete();
    }
};
