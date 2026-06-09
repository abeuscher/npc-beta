<?php

namespace App\Services\ImportExport\Import;

use App\Models\Collection;
use App\Models\NavigationMenu;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\ImportExport\ImportLog;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Recreates a serialized widget + layout tree on an import target (Page or
 * Template). Shared by the page and template importers — both replay the same
 * widget-tree format. Applies graceful-fallback config sanitisation and rewires
 * per-widget media from the bundle's descriptors.
 */
class WidgetTreeHydrator
{
    /**
     * Walk the serialized widget tree and recreate widgets and layouts on the
     * target owner. $label tags warnings (page slug for pages; defaults to the
     * owner name for templates).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function hydrate(Model $owner, array $items, ImportLog $log, BundleMediaArchive $archive, ?string $label = null): void
    {
        foreach ($items as $item) {
            $type = $item['type'] ?? 'widget';

            if ($type === 'layout') {
                $this->hydrateLayoutForOwner($owner, $item, $log, $archive, $label);

                continue;
            }

            $this->hydrateRootWidgetForOwner($owner, $item, $log, $archive, $label);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function hydrateRootWidgetForOwner(Model $owner, array $item, ImportLog $log, BundleMediaArchive $archive, ?string $label = null): void
    {
        $label = $label ?? ($owner->name ?? (string) $owner->getKey());
        $widgetType = $this->resolveWidgetType($item['handle'] ?? null, $label, $log);
        if (! $widgetType) {
            return;
        }

        $widget = $owner->widgets()->create([
            'layout_id'         => null,
            'column_index'      => null,
            'widget_type_id'    => $widgetType->id,
            'label'             => $item['label'] ?? null,
            'config'            => $this->sanitizeWidgetConfig($item['config'] ?? [], $widgetType, $label, $log),
            'query_config'      => $item['query_config'] ?? [],
            'appearance_config' => $item['appearance_config'] ?? [],
            'sort_order'        => $item['sort_order'] ?? 0,
            'is_active'         => $item['is_active'] ?? true,
        ]);

        $this->rewireWidgetMedia($widget, $item['media'] ?? [], $log, $archive);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function hydrateLayoutForOwner(Model $owner, array $item, ImportLog $log, BundleMediaArchive $archive, ?string $label = null): void
    {
        $label = $label ?? ($owner->name ?? (string) $owner->getKey());
        $layout = $owner->layouts()->create([
            'label'             => $item['label'] ?? null,
            'display'           => $item['display'] ?? 'grid',
            'columns'           => $item['columns'] ?? 2,
            'layout_config'     => $item['layout_config'] ?? [],
            'appearance_config' => $item['appearance_config'] ?? [],
            'sort_order'        => $item['sort_order'] ?? 0,
        ]);

        foreach ($item['slots'] ?? [] as $columnIndex => $slotWidgets) {
            foreach ($slotWidgets as $slotItem) {
                $widgetType = $this->resolveWidgetType($slotItem['handle'] ?? null, $label, $log);
                if (! $widgetType) {
                    continue;
                }

                $widget = $owner->widgets()->create([
                    'layout_id'         => $layout->id,
                    'column_index'      => (int) $columnIndex,
                    'widget_type_id'    => $widgetType->id,
                    'label'             => $slotItem['label'] ?? null,
                    'config'            => $this->sanitizeWidgetConfig($slotItem['config'] ?? [], $widgetType, $label, $log),
                    'query_config'      => $slotItem['query_config'] ?? [],
                    'appearance_config' => $slotItem['appearance_config'] ?? [],
                    'sort_order'        => $slotItem['sort_order'] ?? 0,
                    'is_active'         => $slotItem['is_active'] ?? true,
                ]);

                $this->rewireWidgetMedia($widget, $slotItem['media'] ?? [], $log, $archive);
            }
        }
    }

    protected function resolveWidgetType(?string $handle, string $pageSlug, ImportLog $log): ?WidgetType
    {
        if (! $handle) {
            $log->warning("Page \"{$pageSlug}\": widget entry missing handle, skipped.");

            return null;
        }

        $widgetType = WidgetType::where('handle', $handle)->first();
        if (! $widgetType) {
            $log->warning("Page \"{$pageSlug}\": widget type '{$handle}' not found on this install, skipped.");
        }

        return $widgetType;
    }

    /**
     * Apply graceful-fallback rules to a widget config before persisting:
     * - missing collection_handle → cleared, warning logged.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function sanitizeWidgetConfig(array $config, WidgetType $widgetType, string $pageSlug, ImportLog $log): array
    {
        $handle = $config['collection_handle'] ?? null;

        if ($handle) {
            $exists = Collection::where('handle', $handle)
                ->where('is_public', true)
                ->where('is_active', true)
                ->exists();

            if (! $exists) {
                $log->warning("Page \"{$pageSlug}\": collection '{$handle}' not found on this install, widget config cleared.");
                $config['collection_handle'] = '';
            }
        }

        // Nav widget cross-install reference rewiring (session A001/4). The
        // Nav widget's config carries `navigation_menu_id` as a UUID — when
        // the menu is recreated on import with a fresh UUID, the stored id
        // becomes a dangling reference. The exporter emits
        // `navigation_menu_handle` alongside the UUID for portability.
        //
        // We attempt resolution here (best-effort: menu may already exist on
        // the target if the target had it pre-import), but the authoritative
        // pass is `relinkNavWidgetMenus()` after both pages AND nav menus
        // have landed. That post-pass owns the final menu_id + the warning
        // for unresolvable handles, so this stage stays quiet to avoid
        // false-positive warnings on the common case of "menu lands later
        // in the same bundle."
        if ($widgetType->handle === 'nav') {
            $menuHandle = $config['navigation_menu_handle'] ?? null;
            if (is_string($menuHandle) && $menuHandle !== '') {
                $menuId = NavigationMenu::where('handle', $menuHandle)->value('id');
                if ($menuId) {
                    $config['navigation_menu_id'] = $menuId;
                }
                // Unresolved → don't warn here; relinkNavWidgetMenus() runs
                // after the navigation_menus pass and will either resolve
                // or warn-then-null then.
            } elseif (! empty($config['navigation_menu_id'])) {
                // Legacy bundle (pre-A001/4) without the handle — verify the
                // UUID still resolves; if not, null + warn so it renders
                // empty rather than appearing to reference something.
                if (! NavigationMenu::whereKey($config['navigation_menu_id'])->exists()) {
                    $log->warning("Page \"{$pageSlug}\": nav widget's navigation_menu_id '{$config['navigation_menu_id']}' no longer exists on this install, left unassigned.");
                    $config['navigation_menu_id'] = null;
                }
            }
        }

        // Image/video config keys hold media ids that are about to be replaced
        // by the rewiring step. Clear them now so we never serve a stale id.
        // Richtext config keys are sanitised via the same allow-list the model
        // saving boundary uses — defence-in-depth at the import seam.
        foreach ($widgetType->config_schema ?? [] as $field) {
            $type = $field['type'] ?? '';
            $key  = $field['key'] ?? null;
            if (! $key) {
                continue;
            }

            if (in_array($type, ['image', 'video'], true) && isset($config[$key])) {
                $config[$key] = null;
                continue;
            }

            if (($type === 'richtext' || $type === 'table') && isset($config[$key]) && is_string($config[$key])) {
                $config[$key] = HtmlSanitizer::sanitize($config[$key]);
            }
        }

        return $config;
    }

    /**
     * For each media descriptor, look up the file on its disk, attach a new
     * Spatie media row to the widget, and patch the widget config to point at
     * the new media id.
     *
     * @param  array<int, array<string, mixed>>  $descriptors
     */
    protected function rewireWidgetMedia(PageWidget $widget, array $descriptors, ImportLog $log, BundleMediaArchive $archive): void
    {
        if (empty($descriptors)) {
            return;
        }

        $config = $widget->config ?? [];

        foreach ($descriptors as $desc) {
            $key            = $desc['key'] ?? null;
            $collectionName = $desc['collection_name'] ?? ($key ? "config_{$key}" : null);
            $disk           = $desc['disk'] ?? 'public';
            $path           = $desc['path'] ?? null;

            if (! $key || ! $collectionName || ! $path) {
                $log->warning("Widget media descriptor missing key/collection/path, skipped.");

                continue;
            }

            // Defence in depth: refuse path traversal even though the descriptor came from our own exporter.
            if (str_contains($path, '..') || str_starts_with($path, '/')) {
                $log->warning("Widget media descriptor for key '{$key}' has unsafe path, skipped.");

                continue;
            }

            $archiveAbs = $archive->archiveFile($path);
            if ($archiveAbs !== null) {
                $media = $widget
                    ->addMedia($archiveAbs)
                    ->preservingOriginal()
                    ->usingFileName(basename($path))
                    ->toMediaCollection($collectionName, $disk);
            } elseif (Storage::disk($disk)->exists($path)) {
                $media = $widget
                    ->addMediaFromDisk($path, $disk)
                    ->preservingOriginal()
                    ->toMediaCollection($collectionName, $disk);
            } else {
                $log->warning("Media file for key '{$key}' not found at '{$path}' on disk '{$disk}', widget left unset.");

                continue;
            }

            $config[$key] = $media->id;
        }

        $widget->update(['config' => $config]);
    }
}
