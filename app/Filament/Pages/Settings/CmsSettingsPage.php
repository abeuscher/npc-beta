<?php

namespace App\Filament\Pages\Settings;

use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CmsSettingsPage extends Page
{
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'CMS';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.settings.cms-settings-page';

    protected static ?string $title = 'CMS Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'site_name'        => SiteSetting::get('site_name', 'My Organization'),
            'site_description' => SiteSetting::get('site_description', ''),
            'timezone'         => SiteSetting::get('timezone', 'America/Chicago'),
            'contact_email'    => SiteSetting::get('contact_email', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General')
                    ->schema([
                        Forms\Components\TextInput::make('site_name')
                            ->label('Site Name')
                            ->required(),

                        Forms\Components\Textarea::make('site_description')
                            ->label('Site Description')
                            ->nullable()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('timezone')
                            ->label('Timezone')
                            ->options([
                                'America/Chicago'     => 'Central Time (America/Chicago)',
                                'America/New_York'    => 'Eastern Time (America/New_York)',
                                'America/Denver'      => 'Mountain Time (America/Denver)',
                                'America/Los_Angeles' => 'Pacific Time (America/Los_Angeles)',
                                'America/Phoenix'     => 'Arizona (America/Phoenix)',
                                'America/Anchorage'   => 'Alaska (America/Anchorage)',
                                'Pacific/Honolulu'    => 'Hawaii (Pacific/Honolulu)',
                                'UTC'                 => 'UTC',
                            ])
                            ->searchable(),

                        Forms\Components\TextInput::make('contact_email')
                            ->label('Contact Email')
                            ->email()
                            ->nullable(),
                    ])
                    ->columns(2),
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

        SiteSetting::set('site_name', $data['site_name']);
        SiteSetting::set('site_description', $data['site_description'] ?? '');
        SiteSetting::set('timezone', $data['timezone'] ?? 'America/Chicago');
        SiteSetting::set('contact_email', $data['contact_email'] ?? '');

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
