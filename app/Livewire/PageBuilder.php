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
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class PageBuilder extends Component
{
    public string $ownerId = '';

    public string $ownerType = 'page';

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

    /**
     * Mount accepts the legacy `pageId` prop (page-typed) and a new
     * `ownerId` / `ownerType` pair (polymorphic). Callers that still pass
     * `pageId` get page-owner semantics automatically.
     */
    public function mount(string $pageId = '', string $ownerId = '', string $ownerType = 'page'): void
    {
        if ($ownerId !== '') {
            $this->ownerId = $ownerId;
            $this->ownerType = $ownerType;
        } else {
            $this->ownerId = $pageId;
            $this->ownerType = 'page';
        }

        $owner = $this->resolveOwner();

        if ($owner instanceof Page) {
            $this->pageType = $owner->type;
            $this->requiredHandles = WidgetType::requiredForPage($owner->bareSlug());
        }

        $this->widgetTypes = $owner instanceof Template
            ? WidgetType::forPicker(null)
            : WidgetType::forPicker($this->pageType);
    }

    private function resolveOwner(): ?Model
    {
        if (! $this->ownerId) {
            return null;
        }

        return match ($this->ownerType) {
            'template' => Template::find($this->ownerId),
            default    => Page::find($this->ownerId),
        };
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
        $owner = $this->resolveOwner();

        if (! $widgetType || ! $owner) {
            return;
        }

        if (blank($this->addModalLabel)) {
            $count = PageWidget::forOwner($owner)
                ->whereHas('widgetType', fn ($q) => $q->where('id', $widgetTypeId))
                ->count();
            $this->addModalLabel = $widgetType->label . ' ' . ($count + 1);
        }

        if ($this->insertLayoutId) {
            $columnIndex = $this->insertColumnIndex ?? 0;
            $position = (PageWidget::inSlot($owner, $this->insertLayoutId, $columnIndex)
                ->max('sort_order') ?? -1) + 1;
        } else {
            $columnIndex = null;
            $rootCount = PageWidget::inSlot($owner, null, null)->count();
            $position = $this->insertPosition ?? $rootCount;

            PageWidget::inSlot($owner, null, null)
                ->where('sort_order', '>=', $position)
                ->increment('sort_order');
        }

        $def = app(WidgetRegistry::class)->find($widgetType->handle);
        $appearance = $def?->defaultAppearanceConfig() ?? [];

        $newBlock = PageWidget::create([
            'owner_type'        => $owner->getMorphClass(),
            'owner_id'          => $owner->getKey(),
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
        $this->js("window.dispatchEvent(new CustomEvent('widget-created', { detail: { widgetId: '" . $newBlock->id . "', ownerId: '" . $this->ownerId . "', ownerType: '" . $this->ownerType . "' } }))");
    }

    // -------------------------------------------------------------------------
    // Save as Content Template — only meaningful when the owner is a Page.
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

        $source = $this->resolveOwner();
        if (! $source instanceof Page) {
            return;
        }

        $template = Template::create([
            'name'        => $this->saveTemplateName,
            'type'        => 'content',
            'description' => $this->saveTemplateDescription ?: null,
            'is_default'  => false,
            'created_by'  => auth()->id(),
        ]);

        PageWidget::copyOwnedStack($source, $template);

        $name = $this->saveTemplateName;

        $this->showSaveTemplateModal = false;
        $this->saveTemplateName = '';
        $this->saveTemplateDescription = '';

        Notification::make()
            ->title("Template saved: {$name}")
            ->success()
            ->send();

        $this->js("window.dispatchEvent(new CustomEvent('template-saved', { detail: { ownerId: '" . $this->ownerId . "' } }))");
    }

    // -------------------------------------------------------------------------
    // Bootstrap data for the Vue app
    // -------------------------------------------------------------------------

    public function getBootstrapData(): array
    {
        $owner = $this->resolveOwner();
        $page = $owner instanceof Page ? $owner : null;
        $template = $owner instanceof Template ? $owner : null;

        $requiredHandlesForPage = $this->requiredHandles;

        $rootWidgets = $owner
            ? PageWidget::forOwner($owner)
                ->whereNull('layout_id')
                ->where('is_active', true)
                ->with('widgetType')
                ->orderBy('sort_order')
                ->get()
            : collect();

        $layouts = $owner
            ? \App\Models\PageLayout::forOwner($owner)
                ->with(['widgets' => fn ($q) => $q->where('is_active', true)->with('widgetType')->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get()
            : collect();

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
                'owner_type'    => $layout->owner_type,
                'owner_id'      => $layout->owner_id,
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
        // For template-owned stacks we use the default page template's palette.
        $activeTemplate = $page?->template ?? Template::query()->default()->first();
        $themePalette = $activeTemplate?->resolvedPalette() ?? [];

        $legacyWidgets = array_values(array_filter($items, fn ($i) => ($i['type'] ?? '') === 'widget'));

        // Page-only metadata; templates fall back to empty strings.
        $pageTitle  = $page?->title ?? ($template?->name ?? '');
        $pageAuthor = $page?->author?->name ?? '';
        $pageStatus = $page?->status ?? '';
        $pageTags   = $page
            ? $page->tags()->where('type', match ($page->type) {
                'post' => 'post',
                'event' => 'event',
                default => 'page',
            })->pluck('name')->toArray()
            : [];

        $base = rtrim(SiteSetting::get('base_url', config('app.url')), '/');
        $isChromePage = $page && $page->type === 'system' && str_starts_with($page->slug, '_');
        $path = $page
            ? ($isChromePage || $page->slug === 'home' ? '/' : '/' . $page->slug)
            : '';
        $pageUrl = $page ? ($base . $path) : '';

        // Details URL — only for page/post types that have a details sub-page.
        $detailsUrl = null;
        if ($page) {
            if ($page->type === 'post') {
                $detailsUrl = PostResource::getUrl('details', ['record' => $page]);
            } elseif (in_array($page->type, ['default', 'member', 'system'])) {
                $detailsUrl = PageResource::getUrl('details', ['record' => $page]);
            }
        }

        $apiBaseUrl = '/' . $adminPath . '/api/page-builder/' . ($this->ownerType === 'template' ? 'templates' : 'pages') . '/' . $this->ownerId;

        return [
            'owner_id'                => $this->ownerId,
            'owner_type'              => $this->ownerType,
            'page_id'                 => $page?->id ?? '',
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
            'api_base_url'            => $apiBaseUrl,
            'api_lookup_url'          => '/' . $adminPath . '/api/page-builder',
            'inline_image_upload_url' => '/' . $adminPath . '/inline-image-upload',
            'heroicons_url'           => '/' . $adminPath . '/heroicons',
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
