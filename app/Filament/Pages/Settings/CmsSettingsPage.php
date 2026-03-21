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
use Illuminate\Validation\ValidationException;

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
                                    $currentPrefix = SiteSetting::get('blog_prefix', 'news');
                                    if (CmsPage::where('slug', $value)->where('slug', '!=', $currentPrefix)->exists()) {
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

        $oldPrefix = SiteSetting::get('blog_prefix', 'news');
        $newPrefix = $data['blog_prefix'];

        SiteSetting::set('site_name', $data['site_name']);
        SiteSetting::set('blog_prefix', $newPrefix);
        SiteSetting::set('site_description', $data['site_description'] ?? '');
        SiteSetting::set('timezone', $data['timezone'] ?? 'America/Chicago');
        SiteSetting::set('contact_email', $data['contact_email'] ?? '');

        // When the blog prefix changes, update slugs on all type='post' pages
        // and rename the blog index page slug.
        if ($newPrefix !== $oldPrefix) {
            CmsPage::where('type', 'post')
                ->where('slug', 'like', $oldPrefix . '/%')
                ->each(function (CmsPage $page) use ($oldPrefix, $newPrefix) {
                    $page->updateQuietly([
                        'slug' => $newPrefix . '/' . substr($page->slug, strlen($oldPrefix) + 1),
                    ]);
                });

            // Also update the blog index page slug (plain page, no type filter).
            $blogIndexPage = CmsPage::where('slug', $oldPrefix)->first();
            if ($blogIndexPage) {
                $blogIndexPage->updateQuietly(['slug' => $newPrefix]);
            }
        }

        Artisan::call('config:clear');

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
