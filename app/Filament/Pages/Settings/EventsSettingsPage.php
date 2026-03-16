<?php

namespace App\Filament\Pages\Settings;

use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class EventsSettingsPage extends Page
{
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Events';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.settings.events-settings-page';

    protected static ?string $title = 'Event Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'event_auto_publish' => SiteSetting::get('event_auto_publish', 'false') === 'true',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('New Events')
                    ->schema([
                        Forms\Components\Toggle::make('event_auto_publish')
                            ->label('Auto-publish new events')
                            ->helperText('When enabled, the landing page for a newly created event is set to Published. When disabled, it defaults to Draft.'),
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

        SiteSetting::set('event_auto_publish', $data['event_auto_publish'] ? 'true' : 'false');

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
