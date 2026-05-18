<?php

namespace App\Services\ImportExport;

use App\Filament\Pages\DesignSystemPage;
use App\Models\Collection;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\Template;
use App\Models\User;
use App\Services\AssetBuildService;
use App\Services\ColorTokenResolver;
use App\Services\TemplateAppearanceResolver;
use App\Services\TypographyResolver;
use App\Models\WidgetType;
use App\Support\HtmlSanitizer;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContentImporter
{
    /**
     * Absolute path of an extracted bundle's media/ root, set for the duration
     * of a single import() call when the source was a zip. When non-null, media
     * descriptors resolve from the archive first; when null the importer is on
     * the unchanged JSON/local-disk path (media-portability draft decision #2).
     */
    private ?string $mediaRoot = null;

    /**
     * Import a parsed bundle envelope. Validates format_version, then walks
     * templates first and pages second so pages can resolve their template_id.
     * Page templates are re-linked to their chrome pages in a final pass after
     * pages are imported.
     *
     * @param  array<string, mixed>  $bundle
     */
    public function import(array $bundle, ImportLog $log, ?string $mediaRoot = null): void
    {
        $this->validateEnvelope($bundle);

        $this->mediaRoot = $mediaRoot;
        $designImported  = false;
        $seededMediaIds  = [];

        try {
            DB::transaction(function () use ($bundle, $log, &$designImported, &$seededMediaIds) {
                $payload = $bundle['payload'] ?? [];

                // Theme/design runs first so a combined bundle establishes the
                // theme before templates/pages resolve against it.
                if (! empty($payload['design']) && is_array($payload['design'])) {
                    $this->importDesign($payload['design'], $log);
                    $designImported = true;
                }

                // ID-preserving media seed runs before templates/pages so
                // by-reference content resolves against the seeded rows.
                if (! empty($payload['media']) && is_array($payload['media'])) {
                    $seededMediaIds = $this->importMedia($payload['media'], $log);
                }

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
        } finally {
            $this->mediaRoot = null;
        }

        // Rebuild the public CSS bundle outside the transaction (the build
        // server is a 30s HTTP call — never hold a DB transaction across it).
        // Mirrors DesignSystemPage::saveColors()/::save() exactly, incl. the
        // "run php artisan build:public" persistent-notification fallback.
        if ($designImported) {
            $result = app(AssetBuildService::class)->build();
            if (! $result->success) {
                Notification::make()
                    ->title('Theme imported — CSS rebuild failed')
                    ->body('CSS rebuild failed: ' . $result->message . '. Run `php artisan build:public` manually.')
                    ->warning()
                    ->persistent()
                    ->send();
            }
        }

        // Conversions are regenerated on the queue, never shipped (decision
        // #4). Dispatched after commit so a rolled-back seed never enqueues.
        foreach ($seededMediaIds as $mediaId) {
            \App\Jobs\RegenerateMediaConversionsJob::dispatch((int) $mediaId);
        }
    }

    /**
     * payload.design pass — deep-merge each imported design row over its
     * resolver default shape and persist. Never sweeps, replaces wholesale, or
     * zeroes unknown keys: a key absent from the bundle keeps the default
     * concrete value (session 303; the 295 em-rhythm-revert lesson /
     * concrete-values rule). Runs inside the import transaction.
     *
     * @param  array<string, mixed>  $design
     */
    protected function importDesign(array $design, ImportLog $log): void
    {
        $buttonDefaults = DesignSystemPage::defaultButtonStyles();
        $buttonDefaults['icon']        = DesignSystemPage::defaultIconSettings();
        $buttonDefaults['form_append'] = DesignSystemPage::defaultFormAppendSettings();

        $rows = [
            'theme_colors'  => ColorTokenResolver::defaults(),
            'typography'    => TypographyResolver::defaults(),
            'button_styles' => $buttonDefaults,
        ];

        foreach ($rows as $key => $defaults) {
            if (! array_key_exists($key, $design) || ! is_array($design[$key])) {
                continue;
            }

            $incoming = $design[$key];
            if ($key === 'typography') {
                // Same flat→per-breakpoint normaliser the read path applies.
                $incoming = TypographyResolver::migrate($incoming);
            }

            $merged = $this->deepMergeOverDefaults($defaults, $incoming);

            SiteSetting::updateOrCreate(
                ['key' => $key],
                ['value' => json_encode($merged), 'type' => 'json', 'group' => 'design'],
            );
            Cache::forget("site_setting:{$key}");
            $log->info("Theme: imported '{$key}'.");
        }
    }

    /**
     * Recursive merge of imported values over a concrete default base. Never
     * removes a default key; a null override keeps the default (the 295
     * lesson — change values, never null configuration). List arrays are
     * replaced wholesale, associative arrays recursed.
     *
     * @param  array<mixed>  $defaults
     * @param  array<mixed>  $imported
     * @return array<mixed>
     */
    protected function deepMergeOverDefaults(array $defaults, array $imported): array
    {
        foreach ($imported as $key => $value) {
            if (is_array($value)
                && isset($defaults[$key]) && is_array($defaults[$key])
                && ! array_is_list($defaults[$key]) && ! array_is_list($value)) {
                $defaults[$key] = $this->deepMergeOverDefaults($defaults[$key], $value);
            } elseif ($value !== null) {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    /**
     * payload.media pass — posture-B ID-preserving standalone media seed
     * (media-portability draft decisions #3/#4/#6). Raw explicit-id insert so
     * the on-disk path stays {id}/{file_name} and cheap by-reference page
     * bundles resolve after a one-time push. Collision + orphan-owner policies
     * are canonical; the Postgres sequence is reset to MAX(id)+1. Runs inside
     * the import transaction; returns the ids it actually seeded.
     *
     * @param  array<int, array<string, mixed>>  $descriptors
     * @return array<int, int>
     */
    protected function importMedia(array $descriptors, ImportLog $log): array
    {
        $targetDisk = config('media-library.disk_name', 'public');
        $seeded     = [];

        foreach ($descriptors as $desc) {
            $id       = $desc['id'] ?? null;
            $fileName = $desc['file_name'] ?? null;
            $srcPath  = $desc['path'] ?? null;

            if (! $id || ! $fileName || ! $srcPath) {
                $log->warning('Media descriptor missing id/file_name/path, skipped.');

                continue;
            }

            // Collision policy: identical → idempotent skip; divergent → warn
            // and skip (operator re-exports from a clean source). No clobber.
            $existing = DB::table('media')->where('id', $id)->first();
            if ($existing) {
                if (($existing->uuid ?? null) === ($desc['uuid'] ?? null)
                    && $existing->file_name === $fileName) {
                    $log->info("Media #{$id}: already seeded, skipped.");
                } else {
                    $log->warning("Media #{$id}: id exists with a different uuid/file_name on this install — skipped (export from a clean source to resolve).");
                }

                continue;
            }

            // Resolve bytes archive-first, then the source disk.
            $bytes = null;
            $archiveAbs = $this->archiveFile($srcPath);
            if ($archiveAbs !== null) {
                $bytes = file_get_contents($archiveAbs);
            } elseif (! str_contains($srcPath, '..') && ! str_starts_with($srcPath, '/')
                && Storage::disk($desc['disk'] ?? 'public')->exists($srcPath)) {
                $bytes = Storage::disk($desc['disk'] ?? 'public')->get($srcPath);
            }

            if ($bytes === null || $bytes === false) {
                $log->warning("Media #{$id}: file bytes not found in the bundle or on disk, skipped.");

                continue;
            }

            // Orphan-owner policy: park the media even if its original owner
            // row is absent on this install (resolution is path-based).
            $modelType = $desc['model_type'] ?? null;
            $modelId   = $desc['model_id'] ?? null;
            if (is_string($modelType) && class_exists($modelType) && $modelId !== null) {
                try {
                    if (! $modelType::query()->whereKey($modelId)->exists()) {
                        $log->info("Media #{$id}: owner {$modelType}#{$modelId} absent — parked.");
                    }
                } catch (\Throwable) {
                    // Owner check is best-effort/informational only.
                }
            }

            DB::table('media')->insert([
                'id'                    => $id,
                'uuid'                  => $desc['uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
                'model_type'            => $modelType ?? '',
                'model_id'              => $modelId ?? 0,
                'collection_name'       => $desc['collection_name'] ?? 'default',
                'name'                  => $desc['name'] ?? pathinfo($fileName, PATHINFO_FILENAME),
                'file_name'             => $fileName,
                'mime_type'             => $desc['mime_type'] ?? null,
                'disk'                  => $targetDisk,
                'conversions_disk'      => $desc['conversions_disk'] ?? $targetDisk,
                'size'                  => $desc['size'] ?? strlen($bytes),
                'manipulations'         => json_encode($desc['manipulations'] ?? []),
                'custom_properties'     => json_encode($desc['custom_properties'] ?? []),
                'generated_conversions' => json_encode([]),
                'responsive_images'     => json_encode($desc['responsive_images'] ?? []),
                'order_column'          => $desc['order_column'] ?? null,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);

            Storage::disk($targetDisk)->put("{$id}/{$fileName}", $bytes);
            $seeded[] = (int) $id;
        }

        if (! empty($seeded)) {
            // Keep autoincrement ahead of the explicit ids we just inserted.
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('media', 'id'), GREATEST((SELECT COALESCE(MAX(id), 1) FROM media), 1))"
            );
        }

        return $seeded;
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

        // Colour keys from pre-297 export bundles are intentionally ignored —
        // colour is now the site-wide Theme palette, not template-owned.
        //
        // Session-301 columns carried additively + concretely: an older
        // bundle without these keys keeps the template's concrete current
        // value (never null); an unknown scheme string falls back to Default
        // (concrete-values rule — the render-time resolver guards too).
        $incomingScheme = $data['scheme'] ?? null;
        $scheme = is_string($incomingScheme) && in_array($incomingScheme, TemplateAppearanceResolver::schemes(), true)
            ? $incomingScheme
            : ($template->scheme ?: TemplateAppearanceResolver::DEFAULT_SCHEME);

        $template->update([
            'custom_scss' => $data['custom_scss'] ?? null,
            'scheme'      => $scheme,
            'no_header'   => (bool) ($data['no_header'] ?? $template->no_header),
            'no_footer'   => (bool) ($data['no_footer'] ?? $template->no_footer),
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

            $archiveAbs = $this->archiveFile($path);
            if ($archiveAbs !== null) {
                $page
                    ->addMedia($archiveAbs)
                    ->preservingOriginal()
                    ->usingFileName(basename($path))
                    ->toMediaCollection($collectionName, $disk);
            } elseif (Storage::disk($disk)->exists($path)) {
                $page
                    ->addMediaFromDisk($path, $disk)
                    ->preservingOriginal()
                    ->toMediaCollection($collectionName, $disk);
            } else {
                $log->warning("Page \"{$page->slug}\": media file for collection '{$collectionName}' not found at '{$path}' on disk '{$disk}', skipped.");

                continue;
            }
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

            if ($type === 'richtext' && isset($config[$key]) && is_string($config[$key])) {
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

            $archiveAbs = $this->archiveFile($path);
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

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Archive-first resolution (media-portability draft decision #2): the
     * absolute path of a descriptor's file inside the extracted bundle media
     * tree, or null when there is no archive or the file is absent — in which
     * case callers fall back to the unchanged local-disk behaviour. Re-guards
     * traversal and confirms the resolved path stays within the media root.
     */
    private function archiveFile(string $path): ?string
    {
        if ($this->mediaRoot === null || $path === '') {
            return null;
        }
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            return null;
        }

        $abs = $this->mediaRoot . '/' . $path;
        if (! is_file($abs)) {
            return null;
        }

        $real     = realpath($abs);
        $rootReal = realpath($this->mediaRoot);
        if ($real === false || $rootReal === false || ! str_starts_with($real, $rootReal . '/')) {
            return null;
        }

        return $real;
    }

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
