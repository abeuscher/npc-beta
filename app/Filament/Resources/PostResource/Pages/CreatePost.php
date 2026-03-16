<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Models\Post;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $base = Str::slug($data['title']);
        $slug = $base;
        $i    = 2;

        while (Post::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $data['slug'] = $slug;

        return $data;
    }
}
