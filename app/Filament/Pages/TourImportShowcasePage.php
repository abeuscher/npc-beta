<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Demo-safe showcase of the data Importer for the guided product tour.
 *
 * The real ImporterPage is walled off from the `demo` role (bulk data import —
 * DemoRoleLockdownTest). The importer is a multi-step Livewire upload flow, so
 * rather than sandbox it live this page shows a captured screenshot of the real
 * import screen — the same committed-PNG model used for widget thumbnails. The
 * image holds no live data or secrets, so it is safe for the demo prospect.
 *
 * Reachable by any authenticated admin; the tour routes the demo prospect here
 * while a privileged user sees the real Importer. Hidden from navigation.
 */
class TourImportShowcasePage extends Page
{
    protected static string $view = 'filament.pages.tour-import-showcase-page';

    protected static ?string $slug = 'import-showcase';

    protected static ?string $title = 'Import your data';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function getBreadcrumbs(): array
    {
        return [
            Dashboard::getUrl() => 'Dashboard',
            'Import your data',
        ];
    }
}
