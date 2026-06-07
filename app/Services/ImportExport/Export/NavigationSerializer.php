<?php

namespace App\Services\ImportExport\Export;

use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;

/**
 * Serializes NavigationMenu rows (shell + 2-deep item tree, matching the
 * editor) into the bundle's portable navigation shape. Page references travel
 * as page_slug so re-import resolves against the destination's page rows.
 * Session A001.
 */
class NavigationSerializer
{
    /**
     * @param  array<int, string>  $menuIds
     * @return array<int, array<string, mixed>>
     */
    public function serializeMany(array $menuIds): array
    {
        if (empty($menuIds)) {
            return [];
        }

        return NavigationMenu::whereIn('id', $menuIds)
            ->with(['items' => fn ($q) => $q->orderBy('sort_order')])
            ->get()
            ->map(fn (NavigationMenu $menu) => $this->serializeNavigationMenu($menu))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeNavigationMenu(NavigationMenu $menu): array
    {
        $pageSlugsById = Page::whereIn(
            'id',
            $menu->items->pluck('page_id')->filter()->unique()->all()
        )->pluck('slug', 'id')->all();

        $children = [];
        foreach ($menu->items as $item) {
            if ($item->parent_id === null) {
                continue;
            }
            $children[$item->parent_id][] = $item;
        }

        $roots = $menu->items
            ->filter(fn (NavigationItem $i) => $i->parent_id === null)
            ->sortBy('sort_order')
            ->values();

        $serialized = [];
        foreach ($roots as $root) {
            $entry            = $this->serializeNavigationItem($root, $pageSlugsById);
            $entry['children'] = collect($children[$root->id] ?? [])
                ->sortBy('sort_order')
                ->map(fn (NavigationItem $c) => $this->serializeNavigationItem($c, $pageSlugsById))
                ->values()
                ->all();
            $serialized[] = $entry;
        }

        return [
            'menu' => [
                'handle' => $menu->handle,
                'label'  => $menu->label,
            ],
            'items' => $serialized,
        ];
    }

    /**
     * @param  array<string, string>  $pageSlugsById
     * @return array<string, mixed>
     */
    protected function serializeNavigationItem(NavigationItem $item, array $pageSlugsById): array
    {
        return [
            'label'      => $item->label,
            'url'        => $item->url,
            'page_slug'  => $item->page_id ? ($pageSlugsById[$item->page_id] ?? null) : null,
            'target'     => $item->target,
            'is_visible' => (bool) $item->is_visible,
            'sort_order' => (int) $item->sort_order,
        ];
    }
}
