<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class FinanceSettingsPage extends Page
{
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Finance';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.settings.finance-settings-page';

    protected static ?string $title = 'Finance Settings';

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('placeholder')
                            ->label('')
                            ->content('Finance settings will be configured here. QuickBooks and Stripe configuration coming soon.'),
                    ]),
            ])
            ->statePath('data');
    }
}
