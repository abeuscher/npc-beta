<?php

namespace Plugins\Events\Filament\Resources\EventResource\Pages;

use App\Models\Event;
use App\Services\EventLandingPageFactory;
use Plugins\Events\Filament\Resources\EventResource;
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

        $data['slug']      = $slug;
        $data['author_id'] = auth()->id();

        $data = EventResource::computeEndsAt($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        EventLandingPageFactory::createForEvent($this->getRecord());
    }
}
