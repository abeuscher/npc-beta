<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Models\Page;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $blogPrefix = config('site.blog_prefix', 'news');
        $base       = $blogPrefix . '/' . Str::slug($data['title']);
        $slug       = $base;
        $i          = 2;

        while (Page::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $data['slug'] = $slug;
        $data['type'] = 'post';

        return $data;
    }
}
