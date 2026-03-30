<?php

namespace App\Filament\Pages;

use App\Models\Page as PageModel;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use ScssPhp\ScssPhp\Compiler;

class SiteThemePage extends Page
{
    protected static ?string $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Site Theme';

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Site Theme';

    protected static string $view = 'filament.pages.site-theme';

    public ?array $data = [];

    public string $themeScss = '';

    public string $buildOutput = '';

    public bool $buildSuccess = false;

    public ?string $headerPageId = null;

    public ?string $footerPageId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_page') || auth()->user()?->can('edit_theme_scss') ?? false;
    }

    public function getBreadcrumbs(): array
    {
        return [
            static::getUrl() => 'Site Theme',
            'Edit Site Theme',
        ];
    }

    public function mount(): void
    {
        $this->form->fill([
            'public_primary_color' => SiteSetting::get('public_primary_color', '#0172ad'),
            'public_heading_font'  => SiteSetting::get('public_heading_font', ''),
            'public_body_font'     => SiteSetting::get('public_body_font', ''),
            'header_bg_color'      => SiteSetting::get('header_bg_color', '#ffffff'),
            'nav_link_color'       => SiteSetting::get('nav_link_color', '#373c44'),
            'nav_hover_color'      => SiteSetting::get('nav_hover_color', '#0172ad'),
            'nav_active_color'     => SiteSetting::get('nav_active_color', '#0172ad'),
            'footer_bg_color'      => SiteSetting::get('footer_bg_color', '#ffffff'),
        ]);

        $themeFile = resource_path('scss/_custom.scss');
        $this->themeScss = file_exists($themeFile) ? file_get_contents($themeFile) : '';

        $this->headerPageId = PageModel::where('slug', '_header')->value('id');
        $this->footerPageId = PageModel::where('slug', '_footer')->value('id');
    }

    public function form(Form $form): Form
    {
        $fontOptions = [
            ''                                         => '— Default (System) —',
            'system-ui, sans-serif'                    => 'System UI',
            'Georgia, serif'                           => 'Georgia (serif)',
            "'Inter', system-ui, sans-serif"           => 'Inter',
            "'Lato', system-ui, sans-serif"            => 'Lato',
            "'Merriweather', Georgia, serif"           => 'Merriweather',
            "'Montserrat', system-ui, sans-serif"      => 'Montserrat',
            "'Open Sans', system-ui, sans-serif"       => 'Open Sans',
            "'Playfair Display', Georgia, serif"       => 'Playfair Display',
            "'Raleway', system-ui, sans-serif"         => 'Raleway',
            "'Source Sans 3', system-ui, sans-serif"   => 'Source Sans 3',
        ];

        return $form
            ->schema([
                Forms\Components\Group::make([
                    Forms\Components\Section::make('Colors')
                        ->schema([
                            Forms\Components\ColorPicker::make('public_primary_color')
                                ->label('Brand'),

                            Forms\Components\ColorPicker::make('header_bg_color')
                                ->label('Header bg'),

                            Forms\Components\ColorPicker::make('nav_link_color')
                                ->label('Nav link'),

                            Forms\Components\ColorPicker::make('nav_hover_color')
                                ->label('Nav hover'),

                            Forms\Components\ColorPicker::make('nav_active_color')
                                ->label('Nav active'),

                            Forms\Components\ColorPicker::make('footer_bg_color')
                                ->label('Footer bg'),
                        ])
                        ->columns(2),
                ])->columnSpan(1),

                Forms\Components\Group::make([
                    Forms\Components\Section::make('Fonts')
                        ->schema([
                            Forms\Components\Select::make('public_heading_font')
                                ->label('Heading font')
                                ->options($fontOptions),

                            Forms\Components\Select::make('public_body_font')
                                ->label('Body font')
                                ->options($fontOptions),
                        ]),
                ])->columnSpan(1),

            ])
            ->columns(2)
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Appearance')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::set('public_primary_color', $data['public_primary_color'] ?? '');
        SiteSetting::set('public_heading_font',  $data['public_heading_font']  ?? '');
        SiteSetting::set('public_body_font',     $data['public_body_font']     ?? '');
        SiteSetting::set('header_bg_color',      $data['header_bg_color']   ?? '');
        SiteSetting::set('nav_link_color',       $data['nav_link_color']    ?? '');
        SiteSetting::set('nav_hover_color',      $data['nav_hover_color']   ?? '');
        SiteSetting::set('nav_active_color',     $data['nav_active_color']  ?? '');
        SiteSetting::set('footer_bg_color',      $data['footer_bg_color']   ?? '');

        Notification::make()
            ->title('Appearance saved')
            ->success()
            ->send();
    }

    public function saveAndBuild(): void
    {
        $scss = $this->themeScss;

        // Validate SCSS
        try {
            $compiler = new Compiler();
            $compiler->compileString($scss);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('SCSS error')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        // Write to file
        file_put_contents(resource_path('scss/_custom.scss'), $scss);

        // Run Vite build
        $projectRoot = base_path();
        $output = [];
        $exitCode = 0;
        exec("cd " . escapeshellarg($projectRoot) . " && ./node_modules/.bin/vite build 2>&1", $output, $exitCode);

        $this->buildOutput = implode("\n", $output);
        $this->buildSuccess = ($exitCode === 0);

        if ($this->buildSuccess) {
            Notification::make()
                ->title('Theme built successfully.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Build failed')
                ->body(substr($this->buildOutput, 0, 500))
                ->danger()
                ->send();
        }
    }
}
