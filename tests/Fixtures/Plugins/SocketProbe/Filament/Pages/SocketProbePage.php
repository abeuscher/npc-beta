<?php

namespace Tests\Fixtures\Plugins\SocketProbe\Filament\Pages;

use Filament\Pages\Page;

class SocketProbePage extends Page
{
    protected static ?string $slug = 'socket-probe';

    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Socket Probe';

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?int $navigationSort = 97;

    protected static string $view = 'socket-probe::socket-probe-page';

    protected static ?string $title = 'Socket Probe';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('use_socket_probe') ?? false;
    }

    public function getBreadcrumbs(): array
    {
        return [
            static::getUrl() => 'Socket Probe',
            'Main',
        ];
    }
}
