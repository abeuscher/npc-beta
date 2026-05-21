<?php

namespace App\Services;

use App\Http\Resources\WidgetPreviewResource;
use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Database\Eloquent\Model;

class PageTreeBuilder
{
    private ?array $items = null;

    private ?array $tree = null;

    private ?array $libs = null;

    public function __construct(private Model $owner)
    {
    }

    public function tree(): array
    {
        $this->compute();

        return $this->tree;
    }

    public function items(): array
    {
        $this->compute();

        return $this->items;
    }

    public function requiredLibs(): array
    {
        $this->compute();

        return $this->libs;
    }

    private function compute(): void
    {
        if ($this->items !== null) {
            return;
        }

        $requiredHandles = $this->owner instanceof Page
            ? WidgetType::requiredForPage($this->owner->bareSlug())
            : [];

        $rootWidgets = PageWidget::forOwner($this->owner)
            ->whereNull('layout_id')
            ->where('is_active', true)
            ->with(['widgetType', 'owner'])
            ->orderBy('sort_order')
            ->get();

        $layouts = PageLayout::forOwner($this->owner)
            ->with(['widgets' => fn ($q) => $q->where('is_active', true)->with(['widgetType', 'owner'])->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $allLibs = [];
        $items = [];

        foreach ($rootWidgets as $pw) {
            if (! $pw->widgetType) {
                continue;
            }

            $item = $this->formatWidgetWithPreview($pw, $requiredHandles);
            $item['type'] = 'widget';
            $items[] = $item;
            app(WidgetPreviewRenderer::class)->collectLibs($pw, $allLibs);
        }

        foreach ($layouts as $layout) {
            $item = self::formatLayout($layout);
            $slots = [];
            foreach ($layout->widgets as $child) {
                if (! $child->widgetType) {
                    continue;
                }
                $idx = $child->column_index ?? 0;
                $childData = $this->formatWidgetWithPreview($child, $requiredHandles);
                $childData['type'] = 'widget';
                $slots[$idx][] = $childData;
                app(WidgetPreviewRenderer::class)->collectLibs($child, $allLibs);
            }
            $item['slots'] = (object) $slots;
            $items[] = $item;
        }

        usort($items, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        $this->items = $items;
        $this->tree = array_values(array_filter($items, fn ($i) => ($i['type'] ?? '') === 'widget'));
        $this->libs = array_values(array_unique($allLibs));
    }

    public static function formatLayout(PageLayout $layout): array
    {
        return [
            'type'              => 'layout',
            'id'                => $layout->id,
            'owner_type'        => $layout->owner_type,
            'owner_id'          => $layout->owner_id,
            'label'             => $layout->label ?? '',
            'display'           => $layout->display,
            'columns'           => $layout->columns,
            'layout_config'     => $layout->layout_config ?? [],
            'appearance_config' => (object) ($layout->appearance_config ?? []),
            'inline_style'      => app(AppearanceStyleComposer::class)->composeForLayout($layout),
            'sort_order'        => $layout->sort_order ?? 0,
            'slots'             => (object) [],
        ];
    }

    private function formatWidgetWithPreview(PageWidget $pw, array $requiredHandles): array
    {
        return (new WidgetPreviewResource($pw))
            ->withRequiredHandles($requiredHandles)
            ->resolve();
    }
}
