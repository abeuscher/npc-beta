<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;

class HelpIndexPage extends Page
{
    protected static string $view = 'filament.pages.help-index';

    protected static ?string $slug = 'help';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Help';

    public static function helpUrl(): string
    {
        return Filament::getCurrentPanel()->getUrl() . '/help';
    }

    public function getBreadcrumbs(): array
    {
        return [
            'Help',
        ];
    }
}
