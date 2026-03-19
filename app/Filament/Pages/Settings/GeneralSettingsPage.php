<?php

namespace App\Filament\Pages\Settings;

use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

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
            'site_url'            => SiteSetting::get('base_url', 'http://localhost'),
            'admin_brand_name'    => SiteSetting::get('admin_brand_name', ''),
            'admin_primary_color' => SiteSetting::get('admin_primary_color', '#f59e0b'),
            'admin_logo_upload'   => null,
            'dashboard_welcome'   => SiteSetting::get('dashboard_welcome', ''),
            'stripe_api_key'      => SiteSetting::get('stripe_api_key', ''),
            'quickbooks_api_key'  => SiteSetting::get('quickbooks_api_key', ''),
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

                Forms\Components\Section::make('Admin Panel')
                    ->schema([
                        Forms\Components\TextInput::make('admin_brand_name')
                            ->label('Company Name')
                            ->nullable()
                            ->hint('Appears in the admin header beside your logo.')
                            ->columnSpanFull(),

                        Forms\Components\ColorPicker::make('admin_primary_color')
                            ->label('Primary colour')
                            ->helperText('The accent colour used throughout the admin panel. Default: #f59e0b')
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('dashboard_welcome')
                            ->label('Dashboard welcome message')
                            ->nullable()
                            ->columnSpanFull()
                            ->helperText('Displayed at the top of the admin dashboard. Leave blank to hide.'),

                        Forms\Components\Placeholder::make('current_logo_preview')
                            ->label('Current logo')
                            ->content(function () {
                                $path = SiteSetting::get('admin_logo_path', '');
                                if (!$path) {
                                    return 'No logo uploaded.';
                                }
                                return new HtmlString('<img src="' . e(Storage::disk('public')->url($path)) . '" style="max-height:4rem;">');
                            })
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('admin_logo_upload')
                            ->label('Upload new logo')
                            ->nullable()
                            ->disk('public')
                            ->directory('site')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml'])
                            ->columnSpanFull()
                            ->helperText('Replaces the current logo on save.'),

                    ])
                    ->columns(2),

                Forms\Components\Section::make('Integrations')
                    ->schema([
                        Forms\Components\TextInput::make('stripe_api_key')
                            ->label('Stripe API Key')
                            ->nullable()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('quickbooks_api_key')
                            ->label('QuickBooks API Key')
                            ->nullable()
                            ->columnSpanFull(),
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

        SiteSetting::set('base_url', rtrim($data['site_url'], '/'));
        SiteSetting::set('admin_brand_name', trim($data['admin_brand_name'] ?? ''));
        if (!empty($data['admin_logo_upload'])) {
            SiteSetting::set('admin_logo_path', $data['admin_logo_upload']);
        }

        SiteSetting::set('dashboard_welcome', $data['dashboard_welcome'] ?? '');
        SiteSetting::set('admin_primary_color', $data['admin_primary_color'] ?? '#f59e0b');
        SiteSetting::set('stripe_api_key', $data['stripe_api_key'] ?? '');
        SiteSetting::set('quickbooks_api_key', $data['quickbooks_api_key'] ?? '');

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }
}
