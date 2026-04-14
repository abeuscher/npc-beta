<?php

namespace App\Livewire;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\PostResource;
use App\Http\Resources\WidgetPreviewResource;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\Template;
use App\Models\WidgetType;
use App\Services\WidgetPreviewRenderer;
use App\Services\WidgetRegistry;
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
            $columnIndex = $this->insertColumnIndex ?? 0;
            $position = (PageWidget::inSlot($this->pageId, $this->insertLayoutId, $columnIndex)
                ->max('sort_order') ?? -1) + 1;
        } else {
            $columnIndex = null;
            $rootCount = PageWidget::inSlot($this->pageId, null, null)->count();
            $position = $this->insertPosition ?? $rootCount;

            PageWidget::inSlot($this->pageId, null, null)
                ->where('sort_order', '>=', $position)
                ->increment('sort_order');
        }

        $def = app(WidgetRegistry::class)->find($widgetType->handle);
        $appearance = $def?->defaultAppearanceConfig() ?? [];

        $newBlock = PageWidget::create([
            'page_id'           => $this->pageId,
            'layout_id'         => $this->insertLayoutId,
            'column_index'      => $columnIndex,
            'widget_type_id'    => $widgetType->id,
            'label'             => $this->addModalLabel,
            'config'            => [],
            'query_config'      => [],
            'appearance_config' => $appearance,
            'sort_order'        => $position,
            'is_active'         => true,
        ]);

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
        $previewRenderer = app(WidgetPreviewRenderer::class);

        foreach ($rootWidgets as $pw) {
            if (! $pw->widgetType) {
                continue;
            }

            $previewRenderer->collectLibs($pw, $allLibs);

            $items[] = ['type' => 'widget'] + (new WidgetPreviewResource($pw))
                ->withRequiredHandles($requiredHandlesForPage)
                ->resolve();
        }

        foreach ($layouts as $layout) {
            $slots = [];
            foreach ($layout->widgets as $child) {
                if (! $child->widgetType) {
                    continue;
                }
                $idx = $child->column_index ?? 0;
                $previewRenderer->collectLibs($child, $allLibs);

                $slots[$idx][] = ['type' => 'widget'] + (new WidgetPreviewResource($child))
                    ->withRequiredHandles($requiredHandlesForPage)
                    ->resolve();
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
            'theme_editor_url'        => \App\Filament\Pages\DesignSystemPage::getUrl(['activeTab' => 'text-styles']),
        ];
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.page-builder', [
            'bootstrapData' => $this->getBootstrapData(),
        ]);
    }
}
