<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $base = Str::slug($data['title']);
        $slug = $base;
        $i    = 2;

        while (Event::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $data['slug'] = $slug;

        return $data;
    }

    protected function afterCreate(): void
    {
        EventResource::createLandingPageForEvent($this->getRecord());
    }
}
