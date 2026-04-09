<?php

namespace App\Livewire;

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\Template;
use App\Models\WidgetType;
use App\Services\DemoDataService;
use App\Services\WidgetRenderer;
use App\Models\Collection;
use Filament\Notifications\Notification;
use Livewire\Component;

class PageBuilder extends Component
{
    public string $pageId = '';

    private function assertCanEdit(): void
    {
        abort_unless(auth()->user()?->can('update_page'), 403);
    }

    /** @var array<int, array<string, mixed>> */
    public array $widgetTypes = [];

    /** @var array<int, string> Widget handles that are required on this page. */
    protected array $requiredHandles = [];

    public string $pageType = 'default';

    // Add block modal
    public bool $showAddModal = false;
    public ?int $insertPosition = null;
    public string $addModalLabel = '';

    // Save as template modal
    public bool $showSaveTemplateModal = false;
    public string $saveTemplateName = '';
    public string $saveTemplateDescription = '';

    public function mount(string $pageId = ''): void
    {
        $this->pageId = $pageId;

        if ($pageId) {
            $page = Page::find($pageId);
            if ($page) {
                $this->pageType = $page->type;
                $this->requiredHandles = WidgetType::requiredForPage(
                    $this->computeBareSlug($page)
                );
            }
        }

        $this->widgetTypes = WidgetType::forPicker($this->pageType);
    }

    private function computeBareSlug(Page $page): string
    {
        $prefix = match ($page->type) {
            'system' => SiteSetting::get('system_prefix', 'system'),
            'member' => SiteSetting::get('portal_prefix', 'members'),
            default  => '',
        };

        if ($prefix !== '' && str_starts_with($page->slug, $prefix . '/')) {
            return substr($page->slug, strlen($prefix) + 1);
        }

        return $page->slug;
    }

    // -------------------------------------------------------------------------
    // Add block — called by the widget picker modal
    // -------------------------------------------------------------------------

    public function openAddModal(?int $position = null): void
    {
        $this->insertPosition = $position;
        $this->addModalLabel  = '';
        $this->showAddModal   = true;
    }

    public function createBlock(string $widgetTypeId): void
    {
        $this->assertCanEdit();

        $this->validate([
            'addModalLabel' => 'nullable|string|max:255',
        ]);

        $widgetType = WidgetType::find($widgetTypeId);

        if (! $widgetType) {
            return;
        }

        // Auto-generate label if not provided: "Widget Type Label N"
        if (blank($this->addModalLabel)) {
            $count = PageWidget::where('page_id', $this->pageId)
                ->whereHas('widgetType', fn ($q) => $q->where('id', $widgetTypeId))
                ->count();
            $this->addModalLabel = $widgetType->label . ' ' . ($count + 1);
        }

        $defaultConfig = $widgetType->getDefaultConfig();

        $rootCount = PageWidget::where('page_id', $this->pageId)
            ->whereNull('parent_widget_id')
            ->count();
        $position = $this->insertPosition ?? $rootCount;

        // Shift sort_order for blocks at or after the insert position
        PageWidget::where('page_id', $this->pageId)
            ->where('sort_order', '>=', $position)
            ->increment('sort_order');

        $newBlock = PageWidget::create([
            'page_id'        => $this->pageId,
            'widget_type_id' => $widgetType->id,
            'label'          => $this->addModalLabel,
            'config'         => $defaultConfig,
            'query_config'   => [],
            'style_config'   => [
                'background_color' => '#ffffff',
                'text_color'       => '#000000',
            ],
            'sort_order'     => $position,
            'is_active'      => true,
        ]);

        $this->showAddModal   = false;
        $this->insertPosition = null;
        $this->addModalLabel  = '';
        $this->js("window.dispatchEvent(new CustomEvent('widget-created', { detail: { widgetId: '" . $newBlock->id . "' } }))");
    }

    // -------------------------------------------------------------------------
    // Save as Content Template
    // -------------------------------------------------------------------------

    public function openSaveTemplateModal(): void
    {
        $this->saveTemplateName = '';
        $this->saveTemplateDescription = '';
        $this->showSaveTemplateModal = true;
    }

    public function saveAsTemplate(): void
    {
        $this->assertCanEdit();

        $this->validate([
            'saveTemplateName' => 'required|string|max:255',
            'saveTemplateDescription' => 'nullable|string|max:1000',
        ]);

        $definition = PageWidget::serializeStack($this->pageId);

        $name = $this->saveTemplateName;

        Template::create([
            'name'        => $name,
            'type'        => 'content',
            'description' => $this->saveTemplateDescription ?: null,
            'definition'  => $definition,
            'is_default'  => false,
            'created_by'  => auth()->id(),
        ]);

        $this->showSaveTemplateModal = false;
        $this->saveTemplateName = '';
        $this->saveTemplateDescription = '';

        Notification::make()
            ->title("Template saved: {$name}")
            ->success()
            ->send();

        $this->js("window.dispatchEvent(new CustomEvent('template-saved'))");
    }

    // -------------------------------------------------------------------------
    // Bootstrap data for the Vue app
    // -------------------------------------------------------------------------

    public function getBootstrapData(): array
    {
        $page = Page::find($this->pageId);

        // Build the widget tree with preview HTML (same shape as the API response)
        $requiredHandlesForPage = $this->requiredHandles;
        $widgets = PageWidget::where('page_id', $this->pageId)
            ->whereNull('parent_widget_id')
            ->where('is_active', true)
            ->with(['widgetType', 'children.widgetType', 'children.children.widgetType'])
            ->orderBy('sort_order')
            ->get();

        $allLibs = [];
        $formattedWidgets = [];

        foreach ($widgets as $pw) {
            if (! $pw->widgetType) {
                continue;
            }

            $preview = $this->renderWidgetForPreview($pw);
            $this->collectLibs($pw, $allLibs);

            $children = [];
            foreach ($pw->children as $child) {
                if (! $child->is_active || ! $child->widgetType) {
                    continue;
                }
                $idx = $child->column_index ?? 0;
                $children[$idx][] = [
                    'id'                        => $child->id,
                    'widget_type_id'            => $child->widget_type_id,
                    'widget_type_handle'        => $child->widgetType?->handle ?? '',
                    'widget_type_label'         => $child->widgetType?->label ?? 'Unknown',
                    'widget_type_collections'   => $child->widgetType?->collections ?? [],
                    'widget_type_config_schema' => $child->widgetType?->config_schema ?? [],
                    'widget_type_assets'        => $child->widgetType?->assets ?? [],
                    'widget_type_default_open'  => $child->widgetType?->default_open ?? false,
                    'widget_type_required_config' => $child->widgetType?->required_config,
                    'parent_widget_id'          => $child->parent_widget_id,
                    'column_index'              => $child->column_index,
                    'label'                     => $child->label ?? '',
                    'config'                    => $child->config ?? [],
                    'query_config'              => $child->query_config ?? [],
                    'style_config'              => $child->style_config ?? [],
                    'sort_order'                => $child->sort_order ?? 0,
                    'is_active'                 => $child->is_active,
                    'is_required'               => in_array($child->widgetType?->handle ?? '', $requiredHandlesForPage, true),
                    'image_urls'                => $this->resolveWidgetImageUrls($child),
                ];
            }

            $formattedWidgets[] = [
                'id'                        => $pw->id,
                'widget_type_id'            => $pw->widget_type_id,
                'widget_type_handle'        => $pw->widgetType?->handle ?? '',
                'widget_type_label'         => $pw->widgetType?->label ?? 'Unknown',
                'widget_type_collections'   => $pw->widgetType?->collections ?? [],
                'widget_type_config_schema' => $pw->widgetType?->config_schema ?? [],
                'widget_type_assets'        => $pw->widgetType?->assets ?? [],
                'widget_type_default_open'  => $pw->widgetType?->default_open ?? false,
                'widget_type_required_config' => $pw->widgetType?->required_config,
                'parent_widget_id'          => $pw->parent_widget_id,
                'column_index'              => $pw->column_index,
                'label'                     => $pw->label ?? '',
                'config'                    => $pw->config ?? [],
                'query_config'              => $pw->query_config ?? [],
                'style_config'              => $pw->style_config ?? [],
                'sort_order'                => $pw->sort_order ?? 0,
                'is_active'                 => $pw->is_active,
                'is_required'               => in_array($pw->widgetType?->handle ?? '', $requiredHandlesForPage, true),
                'image_urls'                => $this->resolveWidgetImageUrls($pw),
                'preview_html'              => $preview['html'],
                'children'                  => $children,
            ];
        }

        $collections = Collection::where('is_active', true)
            ->orderBy('name')
            ->get(['handle', 'name', 'source_type'])
            ->map(fn ($c) => ['handle' => $c->handle, 'name' => $c->name, 'source_type' => $c->source_type])
            ->values()
            ->toArray();

        $tags = \App\Models\Tag::where('type', 'collection')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->toArray();

        $pages = Page::published()
            ->where('type', 'default')
            ->orderBy('title')
            ->get(['slug', 'title'])
            ->map(fn ($p) => ['slug' => $p->slug, 'title' => $p->title])
            ->toArray();

        $events = \App\Models\Event::published()
            ->orderBy('starts_at')
            ->get(['slug', 'title'])
            ->map(fn ($e) => ['slug' => $e->slug, 'title' => $e->title])
            ->toArray();

        $adminPath = config('filament.path', env('ADMIN_PATH', 'admin'));

        $colorSwatches = json_decode(SiteSetting::get('editor_color_swatches', '[]'), true) ?: [];

        return [
            'page_id'                 => $this->pageId,
            'page_type'               => $this->pageType,
            'widgets'                 => $formattedWidgets,
            'required_libs'           => array_values(array_unique($allLibs)),
            'widget_types'            => $this->widgetTypes,
            'required_handles'        => $this->requiredHandles,
            'collections'             => $collections,
            'tags'                    => $tags,
            'pages'                   => $pages,
            'events'                  => $events,
            'csrf_token'              => csrf_token(),
            'api_base_url'            => '/' . $adminPath . '/api/page-builder',
            'inline_image_upload_url' => '/' . $adminPath . '/inline-image-upload',
            'color_swatches'          => $colorSwatches,
        ];
    }

    // -------------------------------------------------------------------------
    // Preview rendering helpers (used by getBootstrapData)
    // -------------------------------------------------------------------------

    private function resolveWidgetImageUrls(PageWidget $pw): array
    {
        $urls = [];
        $schema = $pw->widgetType?->config_schema ?? [];

        foreach ($schema as $field) {
            if (in_array($field['type'] ?? '', ['image', 'video'])) {
                $media = $pw->getFirstMedia("config_{$field['key']}");
                $urls[$field['key']] = $media?->getUrl();
            }
        }

        return $urls;
    }

    private function collectLibs(PageWidget $pw, array &$libs): void
    {
        $assets = $pw->widgetType?->assets ?? [];
        foreach ($assets['libs'] ?? [] as $lib) {
            $libs[] = $lib;
        }

        foreach ($pw->children as $child) {
            if ($child->is_active) {
                $this->collectLibs($child, $libs);
            }
        }
    }

    private function renderWidgetForPreview(PageWidget $pw): array
    {
        $widgetType = $pw->widgetType;

        try {
            $columnChildren = [];
            if ($widgetType->handle === 'column_widget') {
                $columnChildren = $this->renderColumnChildren($pw);
            }

            $fallbackData = $this->buildDemoCollectionData($pw);
            $result = WidgetRenderer::render($pw, $columnChildren, $fallbackData);

            if ($result['html'] === null) {
                $html = '<div class="widget-preview-notice">No preview available</div>';
            } else {
                $handle = $widgetType->handle;
                $sc = $pw->style_config ?? [];
                $inlineStyle = self::buildInlineStyles($sc);

                $configFullWidth = $pw->config['full_width'] ?? null;
                $styleFullWidth = $sc['full_width'] ?? null;
                $isFullWidth = $configFullWidth !== null ? (bool) $configFullWidth
                    : ($styleFullWidth !== null ? (bool) $styleFullWidth : ($widgetType->full_width ?? false));

                $innerHtml = $isFullWidth
                    ? $result['html']
                    : '<div class="site-container">' . $result['html'] . '</div>';

                $styles = $result['styles'] ? '<style>' . $result['styles'] . '</style>' : '';

                $innerHtml = preg_replace('#<script\b(?![^>]*type=["\']application/json["\'])[^>]*>.*?</script>#si', '', $innerHtml);

                $html = $styles
                    . '<div class="widget widget--' . e($handle) . '"'
                    . ' id="widget-' . e($pw->id) . '"'
                    . ($inlineStyle ? ' style="' . e($inlineStyle) . '"' : '')
                    . '>' . $innerHtml . '</div>';
            }
        } catch (\Throwable $e) {
            $html = '<div class="widget-preview-notice widget-preview-notice--error">Preview error: ' . e($e->getMessage()) . '</div>';
        }

        return [
            'id'               => $pw->id,
            'handle'           => $widgetType->handle,
            'label'            => $pw->label ?? '',
            'widget_type_label' => $widgetType->label,
            'html'             => $html,
        ];
    }

    private function renderColumnChildren(PageWidget $pw): array
    {
        $children = [];

        foreach ($pw->children as $child) {
            if (! $child->is_active) {
                continue;
            }

            $childColumnChildren = [];
            if ($child->widgetType?->handle === 'column_widget') {
                $childColumnChildren = $this->renderColumnChildren($child);
            }

            $childFallback = $this->buildDemoCollectionData($child);
            $result = WidgetRenderer::render($child, $childColumnChildren, $childFallback);

            if ($result['html'] === null) {
                continue;
            }

            $childHandle = $child->widgetType->handle;
            $sc = $child->style_config ?? [];
            $childInlineStyle = self::buildInlineStyles($sc);

            $configFullWidth = $child->config['full_width'] ?? null;
            $styleFullWidth = $sc['full_width'] ?? null;
            $isFullWidth = $configFullWidth !== null ? (bool) $configFullWidth
                : ($styleFullWidth !== null ? (bool) $styleFullWidth : ($child->widgetType->full_width ?? false));

            $idx = $child->column_index ?? 0;
            $children[$idx][] = [
                'handle'       => $childHandle,
                'instance_id'  => $child->id,
                'html'         => $result['html'],
                'css'          => $child->widgetType->css ?? '',
                'js'           => $child->widgetType->js ?? '',
                'style_config' => $sc,
                'full_width'   => $isFullWidth,
            ];
        }

        return $children;
    }

    private static function buildInlineStyles(array $styleConfig): string
    {
        $styleProps = [];

        if (! empty($styleConfig['background_color'])) {
            $styleProps[] = 'background-color:' . $styleConfig['background_color'];
        }
        if (! empty($styleConfig['text_color'])) {
            $styleProps[] = 'color:' . $styleConfig['text_color'];
        }

        $spacingKeys = [
            'padding_top' => 'padding-top', 'padding_right' => 'padding-right',
            'padding_bottom' => 'padding-bottom', 'padding_left' => 'padding-left',
            'margin_top' => 'margin-top', 'margin_right' => 'margin-right',
            'margin_bottom' => 'margin-bottom', 'margin_left' => 'margin-left',
        ];
        foreach ($spacingKeys as $key => $cssProp) {
            $val = isset($styleConfig[$key]) && $styleConfig[$key] !== '' ? (int) $styleConfig[$key] : null;
            if ($val !== null) {
                $styleProps[] = $cssProp . ':' . $val . 'px';
            }
        }

        return implode(';', $styleProps);
    }

    private function buildDemoCollectionData(PageWidget $pw): array
    {
        $widgetType = $pw->widgetType;
        if (! $widgetType || empty($widgetType->collections)) {
            return [];
        }

        $demoService = app(DemoDataService::class);
        $fallback = [];

        foreach ($widgetType->collections as $collSlot) {
            $collHandle = $pw->config['collection_handle'] ?? $collSlot;
            $collection = Collection::where('handle', $collHandle)->first();
            $sourceType = $collection?->source_type ?? $collSlot;
            $fallback[$collSlot] = $demoService->generateCollectionData($sourceType, 3, $collection);
        }

        return $fallback;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.page-builder', [
            'bootstrapData' => $this->getBootstrapData(),
        ]);
    }
}
