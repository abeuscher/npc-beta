<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Models\Page;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $base = Str::slug($data['title']);
        $slug = $base;
        $i    = 2;

        while (Page::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $data['slug']       = $slug;
        $data['meta_title'] = $data['title'];

        return $data;
    }
}
