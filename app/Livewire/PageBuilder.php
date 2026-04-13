<?php

namespace App\Livewire;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\PostResource;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\Template;
use App\Models\WidgetType;
use App\Services\DemoDataService;
use App\Services\WidgetConfigResolver;
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
    public ?string $insertLayoutId = null;
    public ?int $insertColumnIndex = null;
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
                $this->requiredHandles = WidgetType::requiredForPage($page->bareSlug());
            }
        }

        $this->widgetTypes = WidgetType::forPicker($this->pageType);
    }

    // -------------------------------------------------------------------------
    // Add block — called by the widget picker modal
    // -------------------------------------------------------------------------

    public function openAddModal(?int $position = null, ?string $layoutId = null, ?int $columnIndex = null): void
    {
        $this->insertPosition    = $position;
        $this->insertLayoutId    = $layoutId;
        $this->insertColumnIndex = $columnIndex;
        $this->addModalLabel     = '';
        $this->showAddModal      = true;
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

        if ($this->insertLayoutId) {
            // Insert into a column slot
            $columnIndex = $this->insertColumnIndex ?? 0;
            $position = (PageWidget::where('layout_id', $this->insertLayoutId)
                ->where('column_index', $columnIndex)
                ->max('sort_order') ?? -1) + 1;

            $newBlock = PageWidget::create([
                'page_id'           => $this->pageId,
                'layout_id'         => $this->insertLayoutId,
                'column_index'      => $columnIndex,
                'widget_type_id'    => $widgetType->id,
                'label'             => $this->addModalLabel,
                'config'            => [],
                'query_config'      => [],
                'appearance_config' => [
                    'background' => ['color' => '#ffffff'],
                    'text'       => ['color' => '#000000'],
                ],
                'sort_order'        => $position,
                'is_active'         => true,
            ]);
        } else {
            // Insert at root level
            $rootCount = PageWidget::where('page_id', $this->pageId)
                ->whereNull('layout_id')
                ->count();
            $position = $this->insertPosition ?? $rootCount;

            // Shift sort_order for blocks at or after the insert position
            PageWidget::where('page_id', $this->pageId)
                ->whereNull('layout_id')
                ->where('sort_order', '>=', $position)
                ->increment('sort_order');

            $newBlock = PageWidget::create([
                'page_id'           => $this->pageId,
                'widget_type_id'    => $widgetType->id,
                'label'             => $this->addModalLabel,
                'config'            => [],
                'query_config'      => [],
                'appearance_config' => [
                    'background' => ['color' => '#ffffff'],
                    'text'       => ['color' => '#000000'],
                ],
                'sort_order'        => $position,
                'is_active'         => true,
            ]);
        }

        $this->showAddModal      = false;
        $this->insertPosition    = null;
        $this->insertLayoutId    = null;
        $this->insertColumnIndex = null;
        $this->addModalLabel     = '';
        $this->js("window.dispatchEvent(new CustomEvent('widget-created', { detail: { widgetId: '" . $newBlock->id . "', pageId: '" . $this->pageId . "' } }))");
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

        $this->js("window.dispatchEvent(new CustomEvent('template-saved', { detail: { pageId: '" . $this->pageId . "' } }))");
    }

    // -------------------------------------------------------------------------
    // Bootstrap data for the Vue app
    // -------------------------------------------------------------------------

    public function getBootstrapData(): array
    {
        $page = Page::find($this->pageId);

        // Build the merged page flow (root widgets + layouts) with preview HTML
        $requiredHandlesForPage = $this->requiredHandles;

        $rootWidgets = PageWidget::where('page_id', $this->pageId)
            ->whereNull('layout_id')
            ->where('is_active', true)
            ->with('widgetType')
            ->orderBy('sort_order')
            ->get();

        $layouts = \App\Models\PageLayout::where('page_id', $this->pageId)
            ->with(['widgets' => fn ($q) => $q->where('is_active', true)->with('widgetType')->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $allLibs = [];
        $items = [];

        foreach ($rootWidgets as $pw) {
            if (! $pw->widgetType) {
                continue;
            }

            $preview = $this->renderWidgetForPreview($pw);
            $this->collectLibs($pw, $allLibs);

            $items[] = [
                'type'                      => 'widget',
                'id'                        => $pw->id,
                'widget_type_id'            => $pw->widget_type_id,
                'widget_type_handle'        => $pw->widgetType?->handle ?? '',
                'widget_type_label'         => $pw->widgetType?->label ?? 'Unknown',
                'widget_type_collections'   => $pw->widgetType?->collections ?? [],
                'widget_type_config_schema' => $pw->widgetType?->config_schema ?? [],
                'widget_type_assets'        => $pw->widgetType?->assets ?? [],
                'widget_type_default_open'  => $pw->widgetType?->default_open ?? false,
                'widget_type_required_config' => $pw->widgetType?->required_config,
                'layout_id'                 => $pw->layout_id,
                'column_index'              => $pw->column_index,
                'label'                     => $pw->label ?? '',
                'config'                    => $pw->config ?? [],
                'resolved_defaults'         => app(WidgetConfigResolver::class)->resolvedDefaults($pw),
                'query_config'              => $pw->query_config ?? [],
                'appearance_config'         => $pw->appearance_config ?? [],
                'sort_order'                => $pw->sort_order ?? 0,
                'is_active'                 => $pw->is_active,
                'is_required'               => in_array($pw->widgetType?->handle ?? '', $requiredHandlesForPage, true),
                'image_urls'                => $pw->configImageUrls(),
                'preview_html'              => $preview['html'],
            ];
        }

        foreach ($layouts as $layout) {
            $slots = [];
            foreach ($layout->widgets as $child) {
                if (! $child->widgetType) {
                    continue;
                }
                $idx = $child->column_index ?? 0;
                $childPreview = $this->renderWidgetForPreview($child);
                $this->collectLibs($child, $allLibs);

                $slots[$idx][] = [
                    'type'                      => 'widget',
                    'id'                        => $child->id,
                    'widget_type_id'            => $child->widget_type_id,
                    'widget_type_handle'        => $child->widgetType?->handle ?? '',
                    'widget_type_label'         => $child->widgetType?->label ?? 'Unknown',
                    'widget_type_collections'   => $child->widgetType?->collections ?? [],
                    'widget_type_config_schema' => $child->widgetType?->config_schema ?? [],
                    'widget_type_assets'        => $child->widgetType?->assets ?? [],
                    'widget_type_default_open'  => $child->widgetType?->default_open ?? false,
                    'widget_type_required_config' => $child->widgetType?->required_config,
                    'layout_id'                 => $child->layout_id,
                    'column_index'              => $child->column_index,
                    'label'                     => $child->label ?? '',
                    'config'                    => $child->config ?? [],
                    'resolved_defaults'         => app(WidgetConfigResolver::class)->resolvedDefaults($child),
                    'query_config'              => $child->query_config ?? [],
                    'appearance_config'         => $child->appearance_config ?? [],
                    'sort_order'                => $child->sort_order ?? 0,
                    'is_active'                 => $child->is_active,
                    'is_required'               => in_array($child->widgetType?->handle ?? '', $requiredHandlesForPage, true),
                    'image_urls'                => $child->configImageUrls(),
                    'preview_html'              => $childPreview['html'],
                ];
            }

            $items[] = [
                'type'          => 'layout',
                'id'            => $layout->id,
                'page_id'       => $layout->page_id,
                'label'         => $layout->label ?? '',
                'display'       => $layout->display,
                'columns'       => $layout->columns,
                'layout_config' => $layout->layout_config ?? [],
                'sort_order'    => $layout->sort_order ?? 0,
                'slots'         => (object) $slots,
            ];
        }

        usort($items, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

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

        // Theme palette: resolved colors from the page's active template (or the default template).
        $activeTemplate = $page?->template ?? Template::query()->default()->first();
        $themePalette = $activeTemplate?->resolvedPalette() ?? [];

        // Legacy 'widgets' key: root widgets only, kept for current Vue store compatibility
        // until Phase 3 migrates the store to use 'items'.
        $legacyWidgets = array_values(array_filter($items, fn ($i) => ($i['type'] ?? '') === 'widget'));

        // Page metadata for the builder header
        $pageTitle  = $page?->title ?? '';
        $pageAuthor = $page?->author?->name ?? '';
        $pageStatus = $page?->status ?? 'draft';
        $pageTags   = $page
            ? $page->tags()->where('type', match ($page->type) {
                'post' => 'post',
                'event' => 'event',
                default => 'page',
            })->pluck('name')->toArray()
            : [];

        $base = rtrim(SiteSetting::get('base_url', config('app.url')), '/');
        $path = ($page && $page->slug === 'home') ? '/' : '/' . ($page->slug ?? '');
        $pageUrl = $base . $path;

        // Details URL — only for page/post types that have a details sub-page
        $detailsUrl = null;
        if ($page) {
            if ($page->type === 'post') {
                $detailsUrl = PostResource::getUrl('details', ['record' => $page]);
            } elseif (in_array($page->type, ['default', 'member', 'system'])) {
                $detailsUrl = PageResource::getUrl('details', ['record' => $page]);
            }
        }

        return [
            'page_id'                 => $this->pageId,
            'page_type'               => $this->pageType,
            'page_title'              => $pageTitle,
            'page_author'             => $pageAuthor,
            'page_status'             => $pageStatus,
            'page_url'                => $pageUrl,
            'page_tags'               => $pageTags,
            'details_url'             => $detailsUrl,
            'widgets'                 => $legacyWidgets,
            'items'                   => $items,
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
            'theme_palette'           => $themePalette,
        ];
    }

    // -------------------------------------------------------------------------
    // Preview rendering helpers (used by getBootstrapData)
    // -------------------------------------------------------------------------

    private function collectLibs(PageWidget $pw, array &$libs): void
    {
        $assets = $pw->widgetType?->assets ?? [];
        foreach ($assets['libs'] ?? [] as $lib) {
            $libs[] = $lib;
        }
    }

    private function renderWidgetForPreview(PageWidget $pw): array
    {
        $widgetType = $pw->widgetType;

        try {
            $fallbackData = $this->buildDemoCollectionData($pw);
            $result = WidgetRenderer::render($pw, [], $fallbackData);

            if ($result['html'] === null) {
                $html = '<div class="widget-preview-notice">No preview available</div>';
            } else {
                $handle = $widgetType->handle;
                $composed = app(\App\Services\AppearanceStyleComposer::class)->compose($pw);
                $inlineStyle = $composed['inline_style'];

                $configFullWidth = $pw->config['full_width'] ?? null;
                $isFullWidth = $configFullWidth !== null ? (bool) $configFullWidth : $composed['is_full_width'];

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
