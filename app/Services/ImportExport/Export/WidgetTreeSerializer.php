<?php

namespace App\Services\ImportExport\Export;

use App\Models\NavigationMenu;
use App\Models\PageLayout;
use App\Models\PageWidget;
use Illuminate\Database\Eloquent\Model;

/**
 * Serializes an owner's (Page or Template) widget + layout tree into the
 * bundle's portable shape, injecting media descriptors per widget. Shared by
 * the page and template serializers — both emit the same widget-tree format.
 */
class WidgetTreeSerializer
{
    /**
     * Walk any owner's widget+layout tree (Page or Template).
     *
     * @return array<int, array<string, mixed>>
     */
    public function forOwner(Model $owner): array
    {
        $roots = PageWidget::forOwner($owner)
            ->whereNull('layout_id')
            ->with(['widgetType', 'media'])
            ->orderBy('sort_order')
            ->get();

        $layouts = PageLayout::forOwner($owner)
            ->with(['widgets.widgetType', 'widgets.media'])
            ->orderBy('sort_order')
            ->get();

        $items = [];

        foreach ($roots as $pw) {
            $items[] = ['sort' => $pw->sort_order, 'data' => $this->serializeWidget($pw)];
        }

        foreach ($layouts as $layout) {
            $items[] = ['sort' => $layout->sort_order, 'data' => $this->serializeLayout($layout)];
        }

        usort($items, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return array_column($items, 'data');
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeWidget(PageWidget $pw): array
    {
        $entry = [
            'type'              => 'widget',
            'handle'            => $pw->widgetType?->handle,
            'label'             => $pw->label,
            'config'            => $this->serializeWidgetConfigReferences($pw),
            'query_config'      => $pw->query_config ?? [],
            'appearance_config' => $pw->appearance_config ?? [],
            'sort_order'        => $pw->sort_order,
            'is_active'         => $pw->is_active,
            'media'             => $this->serializeWidgetMedia($pw),
        ];

        if ($pw->column_index !== null) {
            $entry['column_index'] = $pw->column_index;
        }

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeLayout(PageLayout $layout): array
    {
        $slots = [];
        foreach ($layout->widgets as $widget) {
            $idx = $widget->column_index ?? 0;
            $slots[$idx][] = $this->serializeWidget($widget);
        }

        return [
            'type'              => 'layout',
            'label'             => $layout->label,
            'display'           => $layout->display,
            'columns'           => $layout->columns,
            'layout_config'     => $layout->layout_config ?? [],
            'appearance_config' => $layout->appearance_config ?? [],
            'sort_order'        => $layout->sort_order,
            'slots'             => $slots,
        ];
    }

    /**
     * Resolve UUID-typed cross-entity references in a widget's config to
     * their human-readable identifiers, alongside the original UUID. Mirror
     * of the page `template_name` / nav `page_slug` patterns: the bundle
     * carries both the original FK and a portable identifier, and the
     * importer prefers the portable one. Session A001/4.
     *
     * Currently scoped to the single known UUID-shaped widget reference:
     * the Nav widget's `navigation_menu_id`. Other entity-reference widgets
     * (BoardMembers, EventDescription, ProductDisplay, WebForm) already
     * use slug/handle in config and round-trip cleanly without rewiring.
     *
     * @return array<string, mixed>
     */
    protected function serializeWidgetConfigReferences(PageWidget $pw): array
    {
        $config = $pw->config ?? [];
        $handle = $pw->widgetType?->handle;

        if ($handle === 'nav' && ! empty($config['navigation_menu_id'])) {
            $menu = NavigationMenu::find($config['navigation_menu_id']);
            if ($menu) {
                $config['navigation_menu_handle'] = $menu->handle;
            }
        }

        return $config;
    }

    /**
     * Build media descriptors for any image/video field on the widget's config_schema
     * that has an attached Spatie media row.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeWidgetMedia(PageWidget $pw): array
    {
        $widgetType = $pw->widgetType;
        if (! $widgetType) {
            return [];
        }

        $descriptors = [];

        foreach ($widgetType->config_schema ?? [] as $field) {
            if (! in_array($field['type'] ?? '', ['image', 'video'], true)) {
                continue;
            }

            $key = $field['key'] ?? null;
            if (! $key) {
                continue;
            }

            $collectionName = "config_{$key}";
            $media = $pw->getFirstMedia($collectionName);
            if (! $media) {
                continue;
            }

            $descriptors[] = [
                'key'             => $key,
                'collection_name' => $collectionName,
                'file_name'       => $media->file_name,
                'disk'            => $media->disk,
                'id'              => $media->id,
                'path'            => $media->getPathRelativeToRoot(),
                'mime_type'       => $media->mime_type,
                'size'            => $media->size,
            ];
        }

        return $descriptors;
    }
}
