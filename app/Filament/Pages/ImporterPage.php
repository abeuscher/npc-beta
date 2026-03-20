<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ImporterPage extends Page
{
    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Importer';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.importer';

    protected static ?string $title = 'Importer';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('import_data');
    }
}
