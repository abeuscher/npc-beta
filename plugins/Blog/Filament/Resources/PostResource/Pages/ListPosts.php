<?php

namespace Plugins\Blog\Filament\Resources\PostResource\Pages;

use App\Filament\Actions\ImportBundleAction;
use Plugins\Blog\Filament\Resources\PostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ImportBundleAction::make(),
        ];
    }
}
