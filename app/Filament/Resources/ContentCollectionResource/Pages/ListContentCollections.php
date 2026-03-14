<?php

namespace App\Filament\Resources\ContentCollectionResource\Pages;

use App\Filament\Resources\ContentCollectionResource;
use Filament\Resources\Pages\ListRecords;

class ListContentCollections extends ListRecords
{
    protected static string $resource = ContentCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
