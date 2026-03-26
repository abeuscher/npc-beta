<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Retired in session 076 — replaced by DonorsPage.
 */
class GenerateTaxReceiptsPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.donors';
}
