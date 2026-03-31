<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Resources\TemplateResource;
use App\Models\Page as PageModel;
use App\Models\PageWidget;
use App\Models\Template;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use ScssPhp\ScssPhp\Compiler;

class EditPageTemplate extends EditRecord
{
    protected static string $resource = TemplateResource::class;

    protected static string $view = 'filament.resources.template-resource.pages.edit-page-template';

    // ── SCSS editor state ───────────────────────────────────────────────────

    public string $themeScss = '';

    public string $buildOutput = '';

    public bool $buildSuccess = false;

    // ── Header / Footer page IDs ────────────────────────────────────────────

    public ?string $headerPageId = null;

    public ?string $footerPageId = null;

    public function getTitle(): string
    {
        return 'Edit Page Template';
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->themeScss   = $this->record->custom_scss ?? '';
        $this->headerPageId = $this->record->resolved('header_page_id');
        $this->footerPageId = $this->record->resolved('footer_page_id');
    }

    public function form(Form $form): Form
    {
        $fontOptions = $this->getFontOptions();

        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->columnSpan(6),

            Forms\Components\Textarea::make('description')
                ->rows(1)
                ->maxLength(1000)
                ->columnSpan(6),

            Forms\Components\Section::make('Colors')
                ->schema([
                    Forms\Components\ColorPicker::make('primary_color')
                        ->label('Brand')
                        ->columnSpan(2),

                    Forms\Components\ColorPicker::make('header_bg_color')
                        ->label('Header bg')
                        ->columnSpan(2),

                    Forms\Components\ColorPicker::make('nav_link_color')
                        ->label('Nav link')
                        ->columnSpan(2),

                    Forms\Components\ColorPicker::make('nav_hover_color')
                        ->label('Nav hover')
                        ->columnSpan(2),

                    Forms\Components\ColorPicker::make('nav_active_color')
                        ->label('Nav active')
                        ->columnSpan(2),

                    Forms\Components\ColorPicker::make('footer_bg_color')
                        ->label('Footer bg')
                        ->columnSpan(2),
                ])
                ->columns(12)
                ->columnSpanFull(),

            Forms\Components\Section::make('Fonts')
                ->schema([
                    Forms\Components\Select::make('heading_font')
                        ->label('Heading font')
                        ->options($fontOptions)
                        ->columnSpan(6),

                    Forms\Components\Select::make('body_font')
                        ->label('Body font')
                        ->options($fontOptions)
                        ->columnSpan(6),
                ])
                ->columns(12)
                ->columnSpanFull(),
        ])->columns(12);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn () => $this->record->is_default),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            TemplateResource::getUrl() => 'Templates',
            'Edit Page Template',
        ];
    }

    // ── Inherit toggles ─────────────────────────────────────────────────────

    public function getIsNonDefaultProperty(): bool
    {
        return ! $this->record->is_default;
    }

    public function getDefaultTemplateProperty(): ?Template
    {
        return Template::page()->where('is_default', true)->first();
    }

    public function clearAppearance(): void
    {
        $this->record->update([
            'primary_color'    => null,
            'heading_font'     => null,
            'body_font'        => null,
            'header_bg_color'  => null,
            'nav_link_color'   => null,
            'nav_hover_color'  => null,
            'nav_active_color' => null,
            'footer_bg_color'  => null,
        ]);

        $this->fillForm();

        Notification::make()->title('Appearance reset to inherit from default')->success()->send();
    }

    // ── SCSS ────────────────────────────────────────────────────────────────

    public function clearScss(): void
    {
        $this->record->update(['custom_scss' => null]);
        $this->themeScss = '';

        Notification::make()->title('SCSS reset to inherit from default')->success()->send();
    }

    public function saveAndBuildScss(): void
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

        // Save to template
        $this->record->update(['custom_scss' => $scss]);

        // For the default template, also write to the file and build
        if ($this->record->is_default) {
            file_put_contents(resource_path('scss/_custom.scss'), $scss);

            $projectRoot = base_path();
            $output = [];
            $exitCode = 0;
            exec("cd " . escapeshellarg($projectRoot) . " && ./node_modules/.bin/vite build 2>&1", $output, $exitCode);

            $this->buildOutput = implode("\n", $output);
            $this->buildSuccess = ($exitCode === 0);

            if ($this->buildSuccess) {
                Notification::make()->title('Theme built successfully.')->success()->send();
            } else {
                Notification::make()
                    ->title('Build failed')
                    ->body(substr($this->buildOutput, 0, 500))
                    ->danger()
                    ->send();
            }
        } else {
            $this->buildOutput = '';
            Notification::make()->title('SCSS saved')->success()->send();
        }
    }

    // ── Custom header / footer ─────────────────────────────────────────────

    public function enableCustomHeader(): void
    {
        $page = $this->createChromePageFor('header');
        $this->record->update(['header_page_id' => $page->id]);
        $this->headerPageId = $page->id;

        Notification::make()->title('Custom header created')->success()->send();
    }

    public function inheritHeader(): void
    {
        $this->record->update(['header_page_id' => null]);
        $default = Template::page()->where('is_default', true)->first();
        $this->headerPageId = $default?->header_page_id;

        Notification::make()->title('Header reset to inherit from default')->success()->send();
    }

    public function enableCustomFooter(): void
    {
        $page = $this->createChromePageFor('footer');
        $this->record->update(['footer_page_id' => $page->id]);
        $this->footerPageId = $page->id;

        Notification::make()->title('Custom footer created')->success()->send();
    }

    public function inheritFooter(): void
    {
        $this->record->update(['footer_page_id' => null]);
        $default = Template::page()->where('is_default', true)->first();
        $this->footerPageId = $default?->footer_page_id;

        Notification::make()->title('Footer reset to inherit from default')->success()->send();
    }

    public function getHasCustomHeaderProperty(): bool
    {
        return ! $this->record->is_default && $this->record->header_page_id !== null;
    }

    public function getHasCustomFooterProperty(): bool
    {
        return ! $this->record->is_default && $this->record->footer_page_id !== null;
    }

    private function createChromePageFor(string $position): PageModel
    {
        $slug = "_{$position}_" . substr($this->record->id, 0, 8);

        $page = PageModel::create([
            'title'     => ucfirst($position) . ' — ' . $this->record->name,
            'slug'      => $slug,
            'type'      => 'system',
            'status'    => 'published',
            'author_id' => auth()->id(),
        ]);

        // Copy widgets from the default template's header/footer
        $default = Template::page()->where('is_default', true)->first();
        $sourcePageId = $position === 'header' ? $default?->header_page_id : $default?->footer_page_id;

        if ($sourcePageId) {
            $this->copyWidgets($sourcePageId, $page->id);
        }

        return $page;
    }

    private function copyWidgets(string $sourcePageId, string $targetPageId, ?string $sourceParentId = null, ?string $targetParentId = null): void
    {
        $widgets = PageWidget::where('page_id', $sourcePageId)
            ->where('parent_widget_id', $sourceParentId)
            ->orderBy('sort_order')
            ->get();

        foreach ($widgets as $widget) {
            $newWidget = PageWidget::create([
                'page_id'          => $targetPageId,
                'parent_widget_id' => $targetParentId,
                'column_index'     => $widget->column_index,
                'widget_type_id'   => $widget->widget_type_id,
                'label'            => $widget->label,
                'config'           => $widget->config,
                'query_config'     => $widget->query_config,
                'style_config'     => $widget->style_config,
                'sort_order'       => $widget->sort_order,
                'is_active'        => $widget->is_active,
            ]);

            $this->copyWidgets($sourcePageId, $targetPageId, $widget->id, $newWidget->id);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getFontOptions(): array
    {
        return [
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
    }
}
