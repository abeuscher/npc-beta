<?php

namespace App\Services\ImportExport\Import;

use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\ImportExport\ImportLog;

/**
 * Imports a serialized navigation menu (upsert by handle; items deleted
 * wholesale then re-inserted roots-first so children resolve their parent ids).
 * Also owns the post-pass that reconciles nav-widget config menu references
 * once both pages and menus have landed. Session A001 / A001/4.
 */
class NavigationImporter
{
    /**
     * Mirrors NavigationMenuResource::saveItems() — the menu is upserted by
     * handle, its items are deleted wholesale, then re-inserted in two passes
     * (roots first to get parent ids, then children). page_slug references
     * resolve against existing Page rows; absent slugs warn and leave page_id
     * null so the link degrades to inert rather than dangling.
     *
     * @param  array<string, mixed>  $data
     */
    public function import(array $data, ImportLog $log): void
    {
        $handle = $data['menu']['handle'] ?? null;
        $label  = $data['menu']['label'] ?? null;

        if (! is_string($handle) || $handle === '' || ! is_string($label)) {
            $log->warning('Navigation menu entry missing handle or label, skipped.');

            return;
        }

        $menu = NavigationMenu::updateOrCreate(
            ['handle' => $handle],
            ['label' => $label],
        );

        NavigationItem::where('navigation_menu_id', $menu->id)->delete();

        $roots = is_array($data['items'] ?? null) ? $data['items'] : [];
        foreach ($roots as $sortOrder => $rootData) {
            $parent = NavigationItem::create($this->navigationItemAttributes(
                $rootData,
                $menu->id,
                parentId:  null,
                sortOrder: (int) ($rootData['sort_order'] ?? $sortOrder),
                log:       $log,
                menuLabel: $label,
            ));

            $children = is_array($rootData['children'] ?? null) ? $rootData['children'] : [];
            foreach ($children as $childSort => $childData) {
                NavigationItem::create($this->navigationItemAttributes(
                    $childData,
                    $menu->id,
                    parentId:  $parent->id,
                    sortOrder: (int) ($childData['sort_order'] ?? $childSort),
                    log:       $log,
                    menuLabel: $label,
                ));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function navigationItemAttributes(array $item, string $menuId, ?string $parentId, int $sortOrder, ImportLog $log, string $menuLabel): array
    {
        $pageId = null;
        if (! empty($item['page_slug'])) {
            $pageId = Page::where('slug', $item['page_slug'])->value('id');
            if (! $pageId) {
                $log->warning("Navigation menu \"{$menuLabel}\": page slug '{$item['page_slug']}' not found, link left without a page reference.");
            }
        }

        return [
            'navigation_menu_id' => $menuId,
            'parent_id'          => $parentId,
            'label'              => $item['label'] ?? '',
            'url'                => $pageId ? null : ($item['url'] ?? null),
            'page_id'            => $pageId,
            'target'             => in_array($item['target'] ?? '_self', ['_self', '_blank'], true) ? $item['target'] : '_self',
            'is_visible'         => (bool) ($item['is_visible'] ?? true),
            'sort_order'         => $sortOrder,
        ];
    }

    /**
     * Walk every nav widget on the install and reconcile its config
     * `navigation_menu_id` against `navigation_menu_handle`. The bundle's
     * widget config carries both for portability; the authoritative
     * resolution happens here, after both the pages pass and the
     * navigation_menus pass have completed inside the same transaction.
     * Mirrors the `TemplateImporter::relinkChrome` pattern. Session A001/4.
     */
    public function relinkNavWidgetMenus(ImportLog $log): void
    {
        $navType = WidgetType::where('handle', 'nav')->first();
        if (! $navType) {
            return;
        }

        // Only widgets whose config carries the portable handle are touched.
        // A nav widget created through the admin UI (no handle in config)
        // has an authoritative `navigation_menu_id` set by the UI and is
        // not a candidate for rewiring.
        $widgets = PageWidget::where('widget_type_id', $navType->id)->get();
        foreach ($widgets as $widget) {
            $config     = $widget->config ?? [];
            $menuHandle = $config['navigation_menu_handle'] ?? null;
            if (! is_string($menuHandle) || $menuHandle === '') {
                continue;
            }

            $menuId = NavigationMenu::where('handle', $menuHandle)->value('id');
            if ($menuId && ($config['navigation_menu_id'] ?? null) === $menuId) {
                continue; // already correct
            }

            if ($menuId) {
                $config['navigation_menu_id'] = $menuId;
                $widget->update(['config' => $config]);
            } else {
                $log->warning("Nav widget '{$widget->id}': navigation menu '{$menuHandle}' not found, left unassigned.");
                if (($config['navigation_menu_id'] ?? null) !== null) {
                    $config['navigation_menu_id'] = null;
                    $widget->update(['config' => $config]);
                }
            }
        }
    }
}
