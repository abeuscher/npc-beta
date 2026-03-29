<?php

namespace App\Filament\Pages\Settings;

use App\Models\SiteSetting;
use App\Services\ImageSizeProfile;
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
            'site_name'          => SiteSetting::get('site_name', 'My Organization'),
            'site_description'   => SiteSetting::get('site_description', ''),
            'timezone'           => SiteSetting::get('timezone', 'America/Chicago'),
            'contact_email'      => SiteSetting::get('contact_email', ''),
            'event_auto_publish'  => SiteSetting::get('event_auto_publish', 'false') === 'true',
            'auto_publish_pages'  => SiteSetting::get('auto_publish_pages', 'true') === 'true',
            'auto_publish_posts'  => SiteSetting::get('auto_publish_posts', 'true') === 'true',
            'image_breakpoints'   => collect(ImageSizeProfile::configuredBreakpoints())
                ->map(fn ($w) => ['width' => $w])
                ->values()
                ->all(),
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
                            ->required()
                            ->columnSpan(6),

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
                            ->searchable()
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('contact_email')
                            ->label('Contact Email')
                            ->email()
                            ->nullable()
                            ->columnSpan(4),

                        Forms\Components\Toggle::make('event_auto_publish')
                            ->label('Auto-publish new events')
                            ->helperText('When enabled, the landing page for a newly created event is set to Published. When disabled, it defaults to Draft.')
                            ->columnSpan(4),

                        Forms\Components\Toggle::make('auto_publish_pages')
                            ->label('Auto-publish new pages')
                            ->helperText('When enabled, newly created pages default to Published.')
                            ->columnSpan(4),

                        Forms\Components\Toggle::make('auto_publish_posts')
                            ->label('Auto-publish new blog posts')
                            ->helperText('When enabled, newly created blog posts default to Published.')
                            ->columnSpan(4),
                    ])
                    ->columns(12),

                Forms\Components\Section::make('Image Sizes')
                    ->description('Responsive breakpoints used when generating optimized image variants. Widths in pixels, sorted largest to smallest. Defaults match Pico.css breakpoints.')
                    ->schema([
                        Forms\Components\Repeater::make('image_breakpoints')
                            ->label('Breakpoints')
                            ->schema([
                                Forms\Components\TextInput::make('width')
                                    ->label('Width')
                                    ->numeric()
                                    ->required()
                                    ->minValue(64)
                                    ->maxValue(3840)
                                    ->suffix('px'),
                            ])
                            ->columns(1)
                            ->defaultItems(0)
                            ->reorderable()
                            ->addActionLabel('Add breakpoint')
                            ->columnSpanFull(),
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

        SiteSetting::set('site_name', $data['site_name']);
        SiteSetting::set('site_description', $data['site_description'] ?? '');
        SiteSetting::set('timezone', $data['timezone'] ?? 'America/Chicago');
        SiteSetting::set('contact_email', $data['contact_email'] ?? '');
        SiteSetting::set('event_auto_publish',  $data['event_auto_publish'] ? 'true' : 'false');
        SiteSetting::set('auto_publish_pages',   $data['auto_publish_pages'] ? 'true' : 'false');
        SiteSetting::set('auto_publish_posts',   $data['auto_publish_posts'] ? 'true' : 'false');

        $breakpoints = collect($data['image_breakpoints'] ?? [])
            ->pluck('width')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v >= 64)
            ->sortDesc()
            ->values()
            ->all();

        $setting = \App\Models\SiteSetting::where('key', 'image_breakpoints')->first();
        if ($setting) {
            $setting->update(['value' => json_encode($breakpoints)]);
        } else {
            \App\Models\SiteSetting::create([
                'key'   => 'image_breakpoints',
                'value' => json_encode($breakpoints),
                'type'  => 'json',
            ]);
        }
        \Illuminate\Support\Facades\Cache::forget('site_setting:image_breakpoints');

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
