<?php

namespace App\Filament\Pages\Settings;

use App\Models\Page as CmsPage;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ScssPhp\ScssPhp\Compiler;

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
            'blog_prefix'      => SiteSetting::get('blog_prefix', 'news'),
            'site_description' => SiteSetting::get('site_description', ''),
            'timezone'         => SiteSetting::get('timezone', 'America/Chicago'),
            'contact_email'    => SiteSetting::get('contact_email', ''),
            'use_pico'         => SiteSetting::get('use_pico', false),
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

                        Forms\Components\TextInput::make('blog_prefix')
                            ->label('Blog Prefix')
                            ->alphaDash()
                            ->required()
                            ->helperText("The URL segment for your blog. Example: 'news' → /news/post-slug. Changes require a cache clear to take effect.")
                            ->rules([
                                fn () => function (string $attribute, string $value, \Closure $fail) {
                                    $reserved = ['admin', 'horizon', 'up', 'login', 'logout', 'register'];
                                    if (in_array(strtolower($value), $reserved, true)) {
                                        $fail("'{$value}' is a reserved word and cannot be used as a blog prefix.");
                                    }
                                    if (CmsPage::where('slug', $value)->exists()) {
                                        $fail("This prefix conflicts with an existing page slug '/{$value}'. Choose a different prefix or rename the page.");
                                    }
                                },
                            ]),

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

                Forms\Components\Section::make('Styles')
                    ->schema([
                        Forms\Components\Toggle::make('use_pico')
                            ->label('Use Pico CSS')
                            ->helperText('Enables Pico CSS, a lightweight classless stylesheet. Good baseline for unstyled installations.'),

                        Forms\Components\FileUpload::make('custom_css_upload')
                            ->label('Custom Stylesheet')
                            ->helperText('Upload a .css or .scss file. SCSS is compiled on upload.')
                            ->acceptedFileTypes(['text/css', 'text/x-scss', 'text/plain'])
                            ->disk('public')
                            ->directory('site')
                            ->visibility('public')
                            ->nullable(),

                        Forms\Components\FileUpload::make('logo_upload')
                            ->label('Logo')
                            ->helperText('Upload a .png, .jpg, or .svg file.')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml'])
                            ->disk('public')
                            ->directory('site')
                            ->visibility('public')
                            ->nullable(),
                    ])
                    ->collapsible()
                    ->collapsed(),
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
        SiteSetting::set('blog_prefix', $data['blog_prefix']);
        SiteSetting::set('site_description', $data['site_description'] ?? '');
        SiteSetting::set('timezone', $data['timezone'] ?? 'America/Chicago');
        SiteSetting::set('contact_email', $data['contact_email'] ?? '');
        SiteSetting::set('use_pico', $data['use_pico'] ? 'true' : 'false');

        if (!empty($data['custom_css_upload'])) {
            $uploadedPath = $data['custom_css_upload'];
            $fullPath     = Storage::disk('public')->path($uploadedPath);
            $extension    = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

            if ($extension === 'scss') {
                try {
                    $compiler = new Compiler();
                    $compiled = $compiler->compileString(file_get_contents($fullPath))->getCss();
                    $cssPath  = 'site/custom.css';
                    Storage::disk('public')->put($cssPath, $compiled);
                    Storage::disk('public')->delete($uploadedPath);
                    SiteSetting::set('custom_css_path', 'storage/' . $cssPath);
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('SCSS compilation failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                    return;
                }
            } else {
                SiteSetting::set('custom_css_path', 'storage/' . $uploadedPath);
            }
        }

        if (!empty($data['logo_upload'])) {
            SiteSetting::set('logo_path', 'storage/' . $data['logo_upload']);
        }

        Artisan::call('config:clear');

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
