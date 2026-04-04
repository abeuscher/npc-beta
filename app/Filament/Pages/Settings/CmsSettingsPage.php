<?php

namespace App\Filament\Pages\Settings;

use App\Models\SiteSetting;
use App\Rules\ValidHtmlSnippet;
use App\Services\Media\ImageSizeProfile;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;

class CmsSettingsPage extends Page
{
    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_cms_settings') ?? false;
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
            'build_server_url'       => SiteSetting::get('build_server_url', '') ?: config('services.build_server.url', ''),
            'build_server_api_key'   => '',
            'favicon_upload'         => null,
            'site_head_snippet'      => SiteSetting::get('site_head_snippet', ''),
            'site_body_open_snippet' => SiteSetting::get('site_body_open_snippet', ''),
            'site_body_snippet'      => SiteSetting::get('site_body_snippet', ''),
            'site_default_og_image'  => null,
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
                            ->columnSpan(8),

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

                Forms\Components\Section::make('Build Server')
                    ->schema([
                        Forms\Components\TextInput::make('build_server_url')
                            ->label('Build Server URL')
                            ->nullable()
                            ->placeholder('http://bundleserver:8080')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('build_server_api_key')
                            ->label('Build Server API Key')
                            ->password()
                            ->extraInputAttributes(['autocomplete' => 'new-password'])
                            ->nullable()
                            ->placeholder(filled(SiteSetting::get('build_server_api_key', '')) ? '••••••••' : '')
                            ->helperText(filled(SiteSetting::get('build_server_api_key', ''))
                                ? 'A key is currently stored. Leave blank to keep the existing key.'
                                : 'No key configured. Falls back to the BUILD_SERVER_API_KEY environment variable.')
                            ->columnSpanFull(),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('test_build_server')
                                ->label('Test Connection')
                                ->icon('heroicon-o-signal')
                                ->color('gray')
                                ->action(function () {
                                    $url = $this->data['build_server_url']
                                        ?: SiteSetting::get('build_server_url', '')
                                        ?: config('services.build_server.url');

                                    $apiKey = SiteSetting::get('build_server_api_key', '')
                                        ?: config('services.build_server.api_key');

                                    if (! $url) {
                                        Notification::make()
                                            ->title('No build server URL configured')
                                            ->danger()
                                            ->send();
                                        return;
                                    }

                                    try {
                                        $response = Http::timeout(10)
                                            ->withToken($apiKey ?: '')
                                            ->get(rtrim($url, '/') . '/health');

                                        if ($response->successful()) {
                                            Notification::make()
                                                ->title('Build server is reachable')
                                                ->success()
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->title('Build server returned HTTP ' . $response->status())
                                                ->danger()
                                                ->send();
                                        }
                                    } catch (\Throwable $e) {
                                        Notification::make()
                                            ->title('Build server unreachable')
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                }),
                        ]),
                    ]),

                Forms\Components\Section::make('Header & Footer Code Snippets')
                    ->schema([
                        Forms\Components\Placeholder::make('current_favicon_preview')
                            ->label('Current favicon')
                            ->content(function () {
                                $path = SiteSetting::get('favicon_path', '');
                                if (! $path) {
                                    return 'No favicon uploaded.';
                                }

                                return new \Illuminate\Support\HtmlString(
                                    '<img src="' . e(\Illuminate\Support\Facades\Storage::disk('public')->url($path)) . '" style="max-height:2rem;">'
                                );
                            })
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('favicon_upload')
                            ->label('Upload favicon')
                            ->helperText('Accepts .ico, .png, or .svg. Replaces the current favicon on save.')
                            ->nullable()
                            ->disk('public')
                            ->directory('site')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/svg+xml'])
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('site_head_snippet')
                            ->label('Site head snippet (before </head>)')
                            ->rows(4)
                            ->extraInputAttributes(['style' => 'font-family:monospace;font-size:0.85rem;'])
                            ->rules([new ValidHtmlSnippet()])
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('site_body_open_snippet')
                            ->label('Snippet below <body> tag')
                            ->helperText('Use this for Google Tag Manager, Google Analytics, or similar scripts that must load immediately after the body tag opens.')
                            ->rows(4)
                            ->extraInputAttributes(['style' => 'font-family:monospace;font-size:0.85rem;'])
                            ->rules([new ValidHtmlSnippet()])
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('site_body_snippet')
                            ->label('Site body snippet (before </body>)')
                            ->rows(4)
                            ->extraInputAttributes(['style' => 'font-family:monospace;font-size:0.85rem;'])
                            ->rules([new ValidHtmlSnippet()])
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('current_og_image_preview')
                            ->label('Current default OG image')
                            ->content(function () {
                                $path = SiteSetting::get('site_default_og_image', '');
                                if (! $path) {
                                    return 'No image set.';
                                }

                                return new \Illuminate\Support\HtmlString(
                                    '<img src="' . e(\Illuminate\Support\Facades\Storage::disk('public')->url($path)) . '" style="max-height:6rem;">'
                                );
                            })
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('site_default_og_image')
                            ->label('Upload default Open Graph image')
                            ->helperText('Fallback image used for social sharing when a page has no OG image set. Replaces the current image on save.')
                            ->nullable()
                            ->disk('public')
                            ->directory('site')
                            ->visibility('public')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Image Sizes')
                    ->description('Responsive breakpoints used when generating optimized image variants. Widths in pixels, sorted largest to smallest.')
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
                            ->grid(5)
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

        SiteSetting::set('build_server_url', $data['build_server_url'] ?? '');

        if (filled($data['build_server_api_key'] ?? '')) {
            $setting = SiteSetting::where('key', 'build_server_api_key')->first();
            if ($setting) {
                $setting->update(['value' => \Illuminate\Support\Facades\Crypt::encryptString($data['build_server_api_key'])]);
            } else {
                SiteSetting::create([
                    'key'   => 'build_server_api_key',
                    'value' => \Illuminate\Support\Facades\Crypt::encryptString($data['build_server_api_key']),
                    'type'  => 'encrypted',
                ]);
            }
            \Illuminate\Support\Facades\Cache::forget('site_setting:build_server_api_key');
        }

        if (! empty($data['favicon_upload'])) {
            SiteSetting::set('favicon_path', $data['favicon_upload']);
        }

        SiteSetting::set('site_head_snippet', $data['site_head_snippet'] ?? '');
        SiteSetting::set('site_body_open_snippet', $data['site_body_open_snippet'] ?? '');
        SiteSetting::set('site_body_snippet', $data['site_body_snippet'] ?? '');
        if (! empty($data['site_default_og_image'])) {
            SiteSetting::set('site_default_og_image', $data['site_default_og_image']);
        }

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
