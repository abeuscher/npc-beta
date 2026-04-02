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
use Illuminate\Support\HtmlString;

class GeneralSettingsPage extends Page
{
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        return $user->hasRole('super_admin') || $user->can('manage_routing_prefixes');
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
            'horizon_enabled'     => SiteSetting::get('horizon_enabled', 'false') === 'true',
            'portal_prefix'       => SiteSetting::get('portal_prefix', 'members'),
            'blog_prefix'         => SiteSetting::get('blog_prefix', 'news'),
            'events_prefix'       => SiteSetting::get('events_prefix', 'events'),
            'system_prefix'       => SiteSetting::get('system_prefix', 'system'),
            'donations_prefix'    => SiteSetting::get('donations_prefix', 'donate'),
            'system_page_content_reset_password' => SiteSetting::get('system_page_content_reset_password', '<h1>Set a new password</h1>'),
            'system_page_content_email_verify'   => SiteSetting::get('system_page_content_email_verify', '<h1>Verify your email</h1>'),
        ]);
    }

    public function form(Form $form): Form
    {
        $isSuperAdmin = auth()->user()?->hasRole('super_admin') ?? false;

        return $form
            ->schema([
                Forms\Components\Section::make('Site')
                    ->schema([
                        Forms\Components\TextInput::make('site_url')
                            ->label('Site URL')
                            ->helperText('The public URL of this installation. Used for generating absolute links. Example: https://yourorg.org')
                            ->url()
                            ->required(),

                        Forms\Components\Toggle::make('horizon_enabled')
                            ->label('Enable Horizon dashboard')
                            ->helperText('When enabled, the queue monitoring dashboard is available at /horizon for super admins. Disabled by default.'),
                    ])
                    ->visible($isSuperAdmin),

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
                    ->columns(2)
                    ->visible($isSuperAdmin),

                Forms\Components\Section::make('Routing')
                    ->description('Changing these prefixes will break existing bookmarked links and any external links published elsewhere. Old URLs will return 404 unless redirects are configured.')
                    ->hidden(fn () => isDemoMode())
                    ->schema([
                        Forms\Components\TextInput::make('blog_prefix')
                            ->label('Blog prefix')
                            ->required()
                            ->alphaDash()
                            ->helperText("The URL segment for blog posts. Example: 'news' → /news/post-slug.")
                            ->rules([
                                fn () => function (string $attribute, string $value, \Closure $fail) {
                                    $reserved = ['admin', 'horizon', 'up', 'login', 'logout', 'register'];
                                    if (in_array(strtolower($value), $reserved, true)) {
                                        $fail("'{$value}' is a reserved word and cannot be used as a blog prefix.");
                                    }
                                    $currentPrefix = SiteSetting::get('blog_prefix', 'news');
                                    if ($value !== $currentPrefix && CmsPage::where('slug', $value)->exists()) {
                                        $fail("This prefix conflicts with an existing page slug '/{$value}'. Choose a different prefix or rename the page.");
                                    }
                                },
                            ]),

                        Forms\Components\TextInput::make('events_prefix')
                            ->label('Events prefix')
                            ->required()
                            ->alphaDash()
                            ->helperText("The URL segment for events. Example: 'events' → /events/event-slug.")
                            ->rules([
                                fn () => function (string $attribute, string $value, \Closure $fail) {
                                    $reserved = ['admin', 'horizon', 'up', 'login', 'logout', 'register'];
                                    if (in_array(strtolower($value), $reserved, true)) {
                                        $fail("'{$value}' is a reserved word and cannot be used as an events prefix.");
                                    }
                                },
                            ]),

                        Forms\Components\TextInput::make('portal_prefix')
                            ->label('Member portal prefix')
                            ->required()
                            ->rules(['alpha_dash'])
                            ->helperText("The URL prefix for the member portal. Example: 'members' → /members/login.")
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('donations_prefix')
                            ->label('Donations prefix')
                            ->required()
                            ->alphaDash()
                            ->helperText("The URL segment for the donation form checkout endpoint. Example: 'donate' → /donate/checkout."),

                        Forms\Components\TextInput::make('system_prefix')
                            ->label('System pages prefix')
                            ->nullable()
                            ->rules([
                                fn () => function (string $attribute, $value, \Closure $fail) {
                                    if ($value !== '' && $value !== null && !preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
                                        $fail("The system prefix may only contain letters, numbers, dashes, and underscores.");
                                    }
                                    if (in_array(strtolower((string) $value), ['admin', 'horizon', 'up', 'login', 'logout', 'register'], true)) {
                                        $fail("'{$value}' is a reserved word and cannot be used as a system prefix.");
                                    }
                                },
                            ])
                            ->helperText("Optional URL prefix for system pages (login, signup, etc.). Leave blank for root-level paths: /login, /signup. Set to e.g. 'system' for /system/login.")
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('System Page Content')
                    ->description('Editable content rendered on system pages that cannot use the CMS page builder.')
                    ->schema([
                        Forms\Components\RichEditor::make('system_page_content_reset_password')
                            ->label('Reset password page')
                            ->nullable()
                            ->columnSpanFull()
                            ->helperText('Rendered above the reset-password form.'),

                        Forms\Components\RichEditor::make('system_page_content_email_verify')
                            ->label('Email verification page')
                            ->nullable()
                            ->columnSpanFull()
                            ->helperText('Rendered above the email address and logout button on the verification notice.'),
                    ])
                    ->visible($isSuperAdmin),

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

        $isSuperAdmin = auth()->user()?->hasRole('super_admin') ?? false;

        // Routing prefixes — available to all who can access this page.
        // Blocked entirely in demo mode — routing changes could break the demo instance.
        $oldBlogPrefix   = SiteSetting::get('blog_prefix', 'news');
        $newBlogPrefix   = isDemoMode() ? $oldBlogPrefix : ($data['blog_prefix'] ?? 'news');
        $oldEventsPrefix = SiteSetting::get('events_prefix', 'events');
        $newEventsPrefix = isDemoMode() ? $oldEventsPrefix : ($data['events_prefix'] ?? 'events');
        $oldSystemPrefix = SiteSetting::get('system_prefix', '');
        $newSystemPrefix = isDemoMode() ? $oldSystemPrefix : ($data['system_prefix'] ?? '');

        if (! isDemoMode()) {
            SiteSetting::set('blog_prefix', $newBlogPrefix);
            SiteSetting::set('events_prefix', $newEventsPrefix);
            SiteSetting::set('portal_prefix', $data['portal_prefix'] ?? 'members');
            SiteSetting::set('system_prefix', $newSystemPrefix);
            SiteSetting::set('donations_prefix', $data['donations_prefix'] ?? 'donate');
        }

        if ($newBlogPrefix !== $oldBlogPrefix) {
            CmsPage::where('type', 'post')
                ->where('slug', 'like', $oldBlogPrefix . '/%')
                ->each(function (CmsPage $page) use ($oldBlogPrefix, $newBlogPrefix) {
                    $page->updateQuietly([
                        'slug' => $newBlogPrefix . '/' . substr($page->slug, strlen($oldBlogPrefix) + 1),
                    ]);
                });

            $blogIndexPage = CmsPage::where('slug', $oldBlogPrefix)->first();
            if ($blogIndexPage) {
                $blogIndexPage->updateQuietly(['slug' => $newBlogPrefix]);
            }
        }

        if ($newEventsPrefix !== $oldEventsPrefix) {
            CmsPage::where('type', 'event')
                ->where('slug', 'like', $oldEventsPrefix . '/%')
                ->each(function (CmsPage $page) use ($oldEventsPrefix, $newEventsPrefix) {
                    $page->updateQuietly([
                        'slug' => $newEventsPrefix . '/' . substr($page->slug, strlen($oldEventsPrefix) + 1),
                    ]);
                });
        }

        if ($newSystemPrefix !== $oldSystemPrefix) {
            CmsPage::where('type', 'system')
                ->each(function (CmsPage $page) use ($oldSystemPrefix, $newSystemPrefix) {
                    if ($oldSystemPrefix !== '' && str_starts_with($page->slug, $oldSystemPrefix . '/')) {
                        $bareSlug = substr($page->slug, strlen($oldSystemPrefix) + 1);
                    } else {
                        $bareSlug = $page->slug;
                    }
                    $newSlug = $newSystemPrefix !== '' ? $newSystemPrefix . '/' . $bareSlug : $bareSlug;
                    $page->updateQuietly(['slug' => $newSlug]);
                });
        }

        // Super-admin-only settings.
        if ($isSuperAdmin) {
            SiteSetting::set('base_url', rtrim($data['site_url'], '/'));
            SiteSetting::set('admin_brand_name', trim($data['admin_brand_name'] ?? ''));
            if (!empty($data['admin_logo_upload'])) {
                SiteSetting::set('admin_logo_path', $data['admin_logo_upload']);
            }
            SiteSetting::set('dashboard_welcome', $data['dashboard_welcome'] ?? '');
            SiteSetting::set('system_page_content_reset_password', $data['system_page_content_reset_password'] ?? '');
            SiteSetting::set('system_page_content_email_verify',   $data['system_page_content_email_verify'] ?? '');
            SiteSetting::set('admin_primary_color', $data['admin_primary_color'] ?? '#f59e0b');
            SiteSetting::set('horizon_enabled', ($data['horizon_enabled'] ?? false) ? 'true' : 'false');
        }

        Artisan::call('config:clear');

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }
}
