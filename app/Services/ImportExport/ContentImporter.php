<?php

namespace App\Services\ImportExport;

use App\Models\Collection;
use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\Template;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContentImporter
{
    /**
     * Import a parsed bundle envelope. Validates format_version, then walks
     * templates first and pages second so pages can resolve their template_id.
     * Page templates are re-linked to their chrome pages in a final pass after
     * pages are imported.
     *
     * @param  array<string, mixed>  $bundle
     */
    public function import(array $bundle, ImportLog $log): void
    {
        $this->validateEnvelope($bundle);

        DB::transaction(function () use ($bundle, $log) {
            $payload = $bundle['payload'] ?? [];

            foreach ($payload['templates'] ?? [] as $templateData) {
                $this->importTemplate($templateData, $log);
            }

            foreach ($payload['pages'] ?? [] as $pageData) {
                $this->importPage($pageData, $log);
            }

            // Re-link page templates to their chrome pages now that the pages exist.
            foreach ($payload['templates'] ?? [] as $templateData) {
                if (($templateData['type'] ?? null) === 'page') {
                    $this->relinkTemplateChrome($templateData, $log);
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $bundle
     */
    protected function validateEnvelope(array $bundle): void
    {
        if (! isset($bundle['format_version'])) {
            throw new InvalidImportBundleException('Bundle is missing format_version.');
        }

        $version = $bundle['format_version'];
        if (! is_string($version) || ! preg_match('/^(\d+)\.\d+\.\d+$/', $version, $m)) {
            throw new InvalidImportBundleException("Invalid format_version: {$version}");
        }

        $major          = (int) $m[1];
        $supportedMajor = (int) explode('.', ContentExporter::FORMAT_VERSION)[0];

        if ($major !== $supportedMajor) {
            throw new InvalidImportBundleException(
                "Unsupported format_version {$version}. This importer accepts {$supportedMajor}.x.y only."
            );
        }

        if (! isset($bundle['payload']) || ! is_array($bundle['payload'])) {
            throw new InvalidImportBundleException('Bundle is missing or has invalid payload.');
        }
    }

    // ── Templates ───────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    protected function importTemplate(array $data, ImportLog $log): void
    {
        $name = $data['name'] ?? null;
        $type = $data['type'] ?? null;

        if (! $name || ! in_array($type, ['page', 'content'], true)) {
            $log->warning('Template entry missing name or type, skipped.');

            return;
        }

        $template = Template::where('name', $name)->where('type', $type)->first();

        if (! $template) {
            $template = Template::create([
                'name'        => $name,
                'type'        => $type,
                'description' => $data['description'] ?? null,
                'is_default'  => false,
                'created_by'  => $this->resolveAuthorId(),
            ]);
        } else {
            $template->update([
                'description' => $data['description'] ?? $template->description,
            ]);
        }

        if ($type === 'content') {
            // Replace the template's widget stack with whatever the bundle carries.
            $template->widgets()->delete();
            $template->layouts()->delete();

            // Widgets array is the new format; `definition` is the pre-polymorphism
            // format, still accepted so older bundles round-trip cleanly.
            $widgets = $data['widgets'] ?? $data['definition'] ?? [];

            foreach ($widgets as $item) {
                $itemType = $item['type'] ?? 'widget';

                if ($itemType === 'layout') {
                    $this->hydrateLayoutForOwner($template, $item, $log);
                } else {
                    $this->hydrateRootWidgetForOwner($template, $item, $log);
                }
            }

            return;
        }

        $template->update([
            'primary_color'    => $data['primary_color'] ?? null,
            'header_bg_color'  => $data['header_bg_color'] ?? null,
            'footer_bg_color'  => $data['footer_bg_color'] ?? null,
            'nav_link_color'   => $data['nav_link_color'] ?? null,
            'nav_hover_color'  => $data['nav_hover_color'] ?? null,
            'nav_active_color' => $data['nav_active_color'] ?? null,
            'custom_scss'      => $data['custom_scss'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function relinkTemplateChrome(array $data, ImportLog $log): void
    {
        $name = $data['name'] ?? null;
        if (! $name) {
            return;
        }

        $template = Template::where('name', $name)->where('type', 'page')->first();
        if (! $template) {
            return;
        }

        $update = [];

        if (! empty($data['header_page_slug'])) {
            $headerPage = Page::where('slug', $data['header_page_slug'])->first();
            if ($headerPage) {
                $update['header_page_id'] = $headerPage->id;
            } else {
                $log->warning("Template \"{$name}\": header page slug '{$data['header_page_slug']}' not found.");
            }
        }

        if (! empty($data['footer_page_slug'])) {
            $footerPage = Page::where('slug', $data['footer_page_slug'])->first();
            if ($footerPage) {
                $update['footer_page_id'] = $footerPage->id;
            } else {
                $log->warning("Template \"{$name}\": footer page slug '{$data['footer_page_slug']}' not found.");
            }
        }

        if (! empty($update)) {
            $template->update($update);
        }
    }

    // ── Pages ───────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    protected function importPage(array $data, ImportLog $log): void
    {
        $slug = $data['slug'] ?? null;
        if (! $slug) {
            $log->warning('Page entry missing slug, skipped.');

            return;
        }

        $existing = Page::withTrashed()->where('slug', $slug)->first();

        $templateId = null;
        if (! empty($data['template_name'])) {
            $template = Template::page()->where('name', $data['template_name'])->first();
            if ($template) {
                $templateId = $template->id;
            } else {
                $log->warning("Page \"{$slug}\": template '{$data['template_name']}' not found, falling back to default.");
                $templateId = Template::page()->where('is_default', true)->value('id');
            }
        }

        $publishedAt = ! empty($data['published_at'])
            ? \Carbon\Carbon::parse($data['published_at'])
            : null;

        $attributes = [
            'title'            => $data['title'] ?? 'Untitled',
            'slug'             => $slug,
            'type'             => $data['type'] ?? 'default',
            'template_id'      => $templateId,
            'status'           => $data['status'] ?? 'draft',
            'meta_title'       => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'noindex'          => $data['noindex'] ?? false,
            'head_snippet'     => $data['head_snippet'] ?? null,
            'body_snippet'     => $data['body_snippet'] ?? null,
            'custom_fields'    => $data['custom_fields'] ?? [],
            'published_at'     => $publishedAt,
        ];

        if ($existing) {
            // Overwrite in place — keep author_id and id, replace everything else.
            // Restore if soft-deleted so the import is visible in the default list.
            if (method_exists($existing, 'trashed') && $existing->trashed()) {
                $existing->restore();
            }

            $existing->update($attributes);
            $page = $existing;

            // Wipe the existing widget tree so the imported one is canonical.
            // Layouts cascade their widgets via FK on delete; root widgets need an explicit delete.
            $page->widgets()->delete();
            $page->layouts()->delete();
        } else {
            $attributes['author_id'] = $this->resolveAuthorId();
            if (! empty($data['id'])) {
                $attributes['id'] = $data['id'];
            }
            $page = Page::create($attributes);
        }

        $this->rewirePageMedia($page, $data['media'] ?? [], $log);
        $this->hydrateWidgets($page, $data['widgets'] ?? [], $log);
    }

    /**
     * Reattach page-level media collections (post_thumbnail, post_header, og_image)
     * from the bundle's media descriptors. Mirrors `rewireWidgetMedia()`.
     *
     * @param  array<int, array<string, mixed>>  $descriptors
     */
    protected function rewirePageMedia(Page $page, array $descriptors, ImportLog $log): void
    {
        if (empty($descriptors)) {
            return;
        }

        foreach ($descriptors as $desc) {
            $collectionName = $desc['collection_name'] ?? null;
            $disk           = $desc['disk'] ?? 'public';
            $path           = $desc['path'] ?? null;

            if (! $collectionName || ! $path) {
                $log->warning("Page \"{$page->slug}\": media descriptor missing collection/path, skipped.");

                continue;
            }

            // Defence in depth: refuse path traversal even though the descriptor came from our own exporter.
            if (str_contains($path, '..') || str_starts_with($path, '/')) {
                $log->warning("Page \"{$page->slug}\": media descriptor for collection '{$collectionName}' has unsafe path, skipped.");

                continue;
            }

            if (! Storage::disk($disk)->exists($path)) {
                $log->warning("Page \"{$page->slug}\": media file for collection '{$collectionName}' not found at '{$path}' on disk '{$disk}', skipped.");

                continue;
            }

            $page
                ->addMediaFromDisk($path, $disk)
                ->preservingOriginal()
                ->toMediaCollection($collectionName, $disk);
        }
    }

    /**
     * Walk the serialized widget tree and recreate widgets and layouts on the target page.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function hydrateWidgets(Page $page, array $items, ImportLog $log): void
    {
        foreach ($items as $item) {
            $type = $item['type'] ?? 'widget';

            if ($type === 'layout') {
                $this->hydrateLayout($page, $item, $log);

                continue;
            }

            $this->hydrateRootWidget($page, $item, $log);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function hydrateRootWidget(Page $page, array $item, ImportLog $log): void
    {
        $this->hydrateRootWidgetForOwner($page, $item, $log, $page->slug);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function hydrateRootWidgetForOwner(\Illuminate\Database\Eloquent\Model $owner, array $item, ImportLog $log, ?string $label = null): void
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

        $this->rewireWidgetMedia($widget, $item['media'] ?? [], $log);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function hydrateLayout(Page $page, array $item, ImportLog $log): void
    {
        $this->hydrateLayoutForOwner($page, $item, $log, $page->slug);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function hydrateLayoutForOwner(\Illuminate\Database\Eloquent\Model $owner, array $item, ImportLog $log, ?string $label = null): void
    {
        $label = $label ?? ($owner->name ?? (string) $owner->getKey());
        $layout = $owner->layouts()->create([
            'label'         => $item['label'] ?? null,
            'display'       => $item['display'] ?? 'grid',
            'columns'       => $item['columns'] ?? 2,
            'layout_config' => $item['layout_config'] ?? [],
            'sort_order'    => $item['sort_order'] ?? 0,
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

                $this->rewireWidgetMedia($widget, $slotItem['media'] ?? [], $log);
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

        // Image/video config keys hold media ids that are about to be replaced
        // by the rewiring step. Clear them now so we never serve a stale id.
        foreach ($widgetType->config_schema ?? [] as $field) {
            if (in_array($field['type'] ?? '', ['image', 'video'], true)) {
                $key = $field['key'] ?? null;
                if ($key && isset($config[$key])) {
                    $config[$key] = null;
                }
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
    protected function rewireWidgetMedia(PageWidget $widget, array $descriptors, ImportLog $log): void
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

            if (! Storage::disk($disk)->exists($path)) {
                $log->warning("Media file for key '{$key}' not found at '{$path}' on disk '{$disk}', widget left unset.");

                continue;
            }

            $media = $widget
                ->addMediaFromDisk($path, $disk)
                ->preservingOriginal()
                ->toMediaCollection($collectionName, $disk);

            $config[$key] = $media->id;
        }

        $widget->update(['config' => $config]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    protected function resolveAuthorId(): int
    {
        if (auth()->check()) {
            return (int) auth()->id();
        }

        $first = User::orderBy('id')->value('id');
        if (! $first) {
            throw new \RuntimeException('No users exist on this install — cannot import pages.');
        }

        return (int) $first;
    }
}
