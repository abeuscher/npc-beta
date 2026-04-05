<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Services\AssetBuildService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class DesignSystemPage extends Page
{
    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Design System';

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.design-system';

    protected static ?string $title = 'Design System';

    public ?string $activeTab = 'buttons';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_cms_settings') ?? false;
    }

    public function getBreadcrumbs(): array
    {
        return [
            'Tools',
            'Design System',
        ];
    }

    public function mount(): void
    {
        $this->loadButtonSettings();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    protected function loadButtonSettings(): void
    {
        $saved = SiteSetting::get('button_styles');

        $defaults = self::defaultButtonStyles();

        $styles = [];
        foreach ($defaults as $handle => $defaultValues) {
            if (is_array($defaultValues)) {
                $styles[$handle] = array_merge($defaultValues, $saved[$handle] ?? []);
            }
        }

        $iconDefaults = self::defaultIconSettings();
        $iconSaved = $saved['icon'] ?? [];
        $styles['icon'] = array_merge($iconDefaults, $iconSaved);

        $appendDefaults = self::defaultFormAppendSettings();
        $appendSaved = $saved['form_append'] ?? [];
        $styles['form_append'] = array_merge($appendDefaults, $appendSaved);

        $this->form->fill(['button_styles' => $styles]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Form schema is built dynamically in the blade via $activeTab
                // Buttons tab fields are rendered here
                ...self::buttonFormSchema(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Rebuild CSS Bundle')
                ->icon('heroicon-o-arrow-path')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $styles = $data['button_styles'] ?? [];

        $variantHandles = array_keys(self::defaultButtonStyles());

        // Validate hex colors — only allow valid hex or null/empty
        foreach ($variantHandles as $handle) {
            if (! isset($styles[$handle])) {
                continue;
            }
            foreach (['bg_color', 'text_color', 'border_color'] as $colorField) {
                $value = $styles[$handle][$colorField] ?? '';
                if (filled($value) && ! preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
                    $styles[$handle][$colorField] = null;
                }
            }
        }

        $setting = SiteSetting::where('key', 'button_styles')->first();
        if ($setting) {
            $setting->update(['value' => json_encode($styles)]);
        } else {
            SiteSetting::create([
                'key'   => 'button_styles',
                'value' => json_encode($styles),
                'type'  => 'json',
                'group' => 'design',
            ]);
        }
        \Illuminate\Support\Facades\Cache::forget('site_setting:button_styles');

        // Trigger public CSS rebuild so changes take effect
        $result = app(AssetBuildService::class)->build();

        if ($result->success) {
            Notification::make()
                ->title('Button styles saved & CSS rebuilt')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Button styles saved')
                ->body('CSS rebuild failed: ' . $result->message . '. Run `php artisan build:public` manually.')
                ->warning()
                ->send();
        }
    }

    public static function defaultButtonStyles(): array
    {
        return [
            'primary' => [
                'border_radius' => 'slightly-rounded',
                'bg_color'      => '#0172ad',
                'text_color'    => '#ffffff',
                'border_color'  => null,
                'border_width'  => '0',
                'hover'         => 'opacity',
                'font_weight'   => '600',
                'text_transform' => 'none',
            ],
            'secondary' => [
                'border_radius' => 'slightly-rounded',
                'bg_color'      => null,
                'text_color'    => '#374151',
                'border_color'  => '#d1d5db',
                'border_width'  => '1px',
                'hover'         => 'opacity',
                'font_weight'   => '600',
                'text_transform' => 'none',
            ],
            'text' => [
                'border_radius' => 'slightly-rounded',
                'bg_color'      => null,
                'text_color'    => '#0172ad',
                'border_color'  => null,
                'border_width'  => '0',
                'hover'         => 'opacity',
                'font_weight'   => '600',
                'text_transform' => 'none',
            ],
            'destructive' => [
                'border_radius' => 'slightly-rounded',
                'bg_color'      => '#dc2626',
                'text_color'    => '#ffffff',
                'border_color'  => null,
                'border_width'  => '0',
                'hover'         => 'darken',
                'font_weight'   => '600',
                'text_transform' => 'none',
            ],
            'link' => [
                'border_radius' => 'slightly-rounded',
                'bg_color'      => null,
                'text_color'    => null,
                'border_color'  => null,
                'border_width'  => '0',
                'hover'         => 'opacity',
                'font_weight'   => '400',
                'text_transform' => 'none',
            ],
        ];
    }

    public static function defaultIconSettings(): array
    {
        return [
            'icon_size'        => 'match',
            'icon_placement'   => 'left',
            'mobile_collapse'  => false,
        ];
    }

    public static function defaultFormAppendSettings(): array
    {
        return [
            'default_variant' => 'primary',
        ];
    }

    protected static function buttonFormSchema(): array
    {
        $variants = [
            'primary'     => 'Primary',
            'secondary'   => 'Secondary',
            'text'        => 'Text',
            'destructive' => 'Destructive',
            'link'        => 'Link',
        ];

        $radiusOptions = [
            'sharp'            => 'Sharp (0)',
            'slightly-rounded' => 'Slightly rounded (0.25em)',
            'rounded'          => 'Rounded (0.5em)',
            'pill'             => 'Pill (999px)',
        ];

        $hoverOptions = [
            'darken'  => 'Darken',
            'lighten' => 'Lighten',
            'opacity' => 'Opacity',
        ];

        $weightOptions = [
            '400' => 'Normal (400)',
            '600' => 'Semibold (600)',
            '700' => 'Bold (700)',
        ];

        $transformOptions = [
            'none'      => 'None',
            'uppercase' => 'Uppercase',
        ];

        $borderWidthOptions = [
            '0'   => 'None',
            '1px' => '1px',
            '2px' => '2px',
        ];

        $sections = [];
        foreach ($variants as $handle => $label) {
            $sections[] = Forms\Components\Section::make($label)
                ->schema([
                    Forms\Components\Select::make("button_styles.{$handle}.border_radius")
                        ->label('Border Radius')
                        ->options($radiusOptions)
                        ->columnSpan(3),

                    Forms\Components\TextInput::make("button_styles.{$handle}.bg_color")
                        ->label('Background Color')
                        ->type('color')
                        ->columnSpan(3),

                    Forms\Components\TextInput::make("button_styles.{$handle}.text_color")
                        ->label('Text Color')
                        ->type('color')
                        ->columnSpan(3),

                    Forms\Components\TextInput::make("button_styles.{$handle}.border_color")
                        ->label('Border Color')
                        ->type('color')
                        ->columnSpan(3),

                    Forms\Components\Select::make("button_styles.{$handle}.border_width")
                        ->label('Border Width')
                        ->options($borderWidthOptions)
                        ->columnSpan(3),

                    Forms\Components\Select::make("button_styles.{$handle}.hover")
                        ->label('Hover Behavior')
                        ->options($hoverOptions)
                        ->columnSpan(3),

                    Forms\Components\Select::make("button_styles.{$handle}.font_weight")
                        ->label('Font Weight')
                        ->options($weightOptions)
                        ->columnSpan(3),

                    Forms\Components\Select::make("button_styles.{$handle}.text_transform")
                        ->label('Text Transform')
                        ->options($transformOptions)
                        ->columnSpan(3),

                    Forms\Components\View::make('filament.pages.partials.button-preview')
                        ->viewData(['variant' => $handle, 'label' => $label])
                        ->columnSpanFull(),
                ])
                ->columns(12)
                ->collapsed()
                ->collapsible();
        }

        // Icon buttons section
        $sections[] = Forms\Components\Section::make('Icon Buttons')
            ->description('Controls for buttons that include icons alongside or instead of text.')
            ->schema([
                Forms\Components\Select::make('button_styles.icon.icon_size')
                    ->label('Icon Size')
                    ->options([
                        'match'   => 'Match text size',
                        'larger'  => 'Slightly larger',
                        '1.5x'   => '1.5× text size',
                    ])
                    ->columnSpan(4),

                Forms\Components\Select::make('button_styles.icon.icon_placement')
                    ->label('Icon Placement')
                    ->options([
                        'left'      => 'Left of text',
                        'right'     => 'Right of text',
                        'icon-only' => 'Icon only',
                    ])
                    ->columnSpan(4),

                Forms\Components\Toggle::make('button_styles.icon.mobile_collapse')
                    ->label('Collapse to icon-only on mobile')
                    ->helperText('Icon+text buttons become icon-only below the medium breakpoint.')
                    ->columnSpan(4),
            ])
            ->columns(12)
            ->collapsed()
            ->collapsible();

        // Form-append buttons section
        $sections[] = Forms\Components\Section::make('Form-Append Buttons')
            ->description('Buttons that attach to form inputs (search bars, filter fields, URL inputs). They inherit border-radius from the adjacent input on the joined side.')
            ->schema([
                Forms\Components\Select::make('button_styles.form_append.default_variant')
                    ->label('Default Variant')
                    ->helperText('Which button variant to use for form-append buttons by default.')
                    ->options([
                        'primary'     => 'Primary',
                        'secondary'   => 'Secondary',
                        'text'        => 'Text',
                        'destructive' => 'Destructive',
                    ])
                    ->columnSpan(4),
            ])
            ->columns(12)
            ->collapsed()
            ->collapsible();

        return $sections;
    }
}
