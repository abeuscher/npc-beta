<?php

namespace App\Filament\Pages\Settings;

use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GeneralSettingsPage extends Page
{
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'General';

    protected static ?string $navigationIcon = 'heroicon-o-cog';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.settings.general-settings-page';

    protected static ?string $title = 'General Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'site_url' => SiteSetting::get('base_url', 'http://localhost'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Site')
                    ->schema([
                        Forms\Components\TextInput::make('site_url')
                            ->label('Site URL')
                            ->helperText('The public URL of this installation. Used for generating absolute links. Example: https://yourorg.org')
                            ->url()
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::set('base_url', rtrim($data['site_url'], '/'));

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
