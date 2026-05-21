<?php

namespace App\Services\ImportExport;

use App\Filament\Pages\DesignSystemPage;
use App\Models\Collection;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
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
     * Per-import flag (session 309). When false, pages whose slug already
     * exists on this install are skipped with a warning instead of being
     * overwritten in place. Defaults to true so direct service callers keep
     * the existing overwrite semantics; the import UI passes false when the
     * operator declines the "Replace duplicate pages" checkbox.
     */
    private bool $replaceDuplicatePages = true;

    /**
     * Canonical default opts for import(). Kept in one place so analyze()/UI
     * and the service share the same baseline.
     *
     * @return array{merge_design: bool, import_media: bool, replace_duplicate_pages: bool}
     */
    public static function defaultImportOpts(): array
    {
        return [
            'merge_design'            => false,
            'import_media'            => true,
            'import_pages'            => true,
            'replace_duplicate_pages' => true,
            'import_navigation'       => true,
        ];
    }

    /**
     * Pre-flight inspection (session 309). Validates the envelope and reports
     * what the bundle carries so the import UI can decide which questions to
     * ask. Pure inspection — never writes to the DB, never touches storage,
     * never runs the import. Throws InvalidImportBundleException on a malformed
     * envelope so the caller can render the same error the import path would.
     *
     * @param  array<string, mixed>  $bundle
     * @return array{
     *     has_design: bool,
     *     design_keys: array<int, string>,
     *     has_media: bool,
     *     media_count: int,
     *     pages: array<int, array{slug: string, exists_locally: bool}>,
     *     templates: array<int, array{name: string, type: string, exists_locally: bool}>,
     *     navigation_menus: array<int, array{handle: string, label: string, items_count: int, exists_locally: bool}>
     * }
     */
    public function analyze(array $bundle): array
    {
        $this->validateEnvelope($bundle);

        $payload = $bundle['payload'] ?? [];

        $designKeys = [];
        if (! empty($payload['design']) && is_array($payload['design'])) {
            foreach (['theme_colors', 'typography', 'button_styles'] as $key) {
                if (array_key_exists($key, $payload['design']) && is_array($payload['design'][$key])) {
                    $designKeys[] = $key;
                }
            }
        }

        $pages = [];
        $slugs = [];
        foreach ($payload['pages'] ?? [] as $entry) {
            $slug = $entry['slug'] ?? null;
            if (! is_string($slug) || $slug === '') {
                continue;
            }
            $pages[] = ['slug' => $slug, 'exists_locally' => false];
            $slugs[] = $slug;
        }
        if (! empty($slugs)) {
            $existingSlugs = Page::withTrashed()
                ->whereIn('slug', $slugs)
                ->pluck('slug')
                ->all();
            $existingSet = array_flip($existingSlugs);
            foreach ($pages as &$row) {
                $row['exists_locally'] = isset($existingSet[$row['slug']]);
            }
            unset($row);
        }

        $templates    = [];
        $tplLookups   = [];
        foreach ($payload['templates'] ?? [] as $entry) {
            $name = $entry['name'] ?? null;
            $type = $entry['type'] ?? null;
            if (! is_string($name) || $name === '' || ! is_string($type)) {
                continue;
            }
            $templates[]  = ['name' => $name, 'type' => $type, 'exists_locally' => false];
            $tplLookups[] = ['name' => $name, 'type' => $type];
        }
        if (! empty($tplLookups)) {
            $existingTemplates = Template::query()
                ->where(function ($q) use ($tplLookups) {
                    foreach ($tplLookups as $row) {
                        $q->orWhere(function ($qq) use ($row) {
                            $qq->where('name', $row['name'])->where('type', $row['type']);
                        });
                    }
                })
                ->get(['name', 'type'])
                ->map(fn ($t) => $t->name . '|' . $t->type)
                ->flip();
            foreach ($templates as &$row) {
                $row['exists_locally'] = isset($existingTemplates[$row['name'] . '|' . $row['type']]);
            }
            unset($row);
        }

        $hasMedia   = ! empty($payload['media']) && is_array($payload['media']);
        $mediaCount = $hasMedia ? count($payload['media']) : 0;

        $navigationMenus = [];
        $navHandles      = [];
        foreach ($payload['navigation_menus'] ?? [] as $entry) {
            $handle = $entry['menu']['handle'] ?? null;
            $label  = $entry['menu']['label'] ?? null;
            if (! is_string($handle) || $handle === '' || ! is_string($label)) {
                continue;
            }
            $items = is_array($entry['items'] ?? null) ? $entry['items'] : [];
            $itemsCount = 0;
            foreach ($items as $root) {
                $itemsCount++;
                if (! empty($root['children']) && is_array($root['children'])) {
                    $itemsCount += count($root['children']);
                }
            }
            $navigationMenus[] = [
                'handle'         => $handle,
                'label'          => $label,
                'items_count'    => $itemsCount,
                'exists_locally' => false,
            ];
            $navHandles[] = $handle;
        }
        if (! empty($navHandles)) {
            $existingHandles = NavigationMenu::whereIn('handle', $navHandles)
                ->pluck('handle')
                ->all();
            $existingSet = array_flip($existingHandles);
            foreach ($navigationMenus as &$row) {
                $row['exists_locally'] = isset($existingSet[$row['handle']]);
            }
            unset($row);
        }

        return [
            'has_design'       => $designKeys !== [],
            'design_keys'      => $designKeys,
            'has_media'        => $hasMedia,
            'media_count'      => $mediaCount,
            'pages'            => $pages,
            'templates'        => $templates,
            'navigation_menus' => $navigationMenus,
        ];
    }

    /**
     * Import a parsed bundle envelope. Validates format_version, then walks
     * templates first and pages second so pages can resolve their template_id.
     * Page templates are re-linked to their chrome pages in a final pass after
     * pages are imported.
     *
     * Session 309 added the $opts gate. Each opt-in branch (design merge, media
     * seed, duplicate-page overwrite) is now controlled by an explicit flag so
     * the import UI can surface the choice. merge_design defaults to FALSE — a
     * behaviour flip so old callers that import a bundle containing
     * payload.design no longer silently overwrite the Theme editor's settings.
     *
     * @param  array<string, mixed>  $bundle
     * @param  array{merge_design?: bool, import_media?: bool, replace_duplicate_pages?: bool}  $opts
     */
    public function import(array $bundle, ImportLog $log, array $opts = [], ?string $mediaRoot = null): void
    {
        $this->validateEnvelope($bundle);

        $opts = array_replace(self::defaultImportOpts(), $opts);

        $this->mediaRoot                = $mediaRoot;
        $this->replaceDuplicatePages    = (bool) $opts['replace_duplicate_pages'];
        $designImported                 = false;
        $seededMediaIds                 = [];

        try {
            DB::transaction(function () use ($bundle, $log, $opts, &$designImported, &$seededMediaIds) {
                $payload = $bundle['payload'] ?? [];

                // Theme/design runs first so a combined bundle establishes the
                // theme before templates/pages resolve against it. Gated on
                // merge_design (session 309 — default off).
                if (! empty($payload['design']) && is_array($payload['design'])) {
                    if ($opts['merge_design']) {
                        $this->importDesign($payload['design'], $log);
                        $designImported = true;
                    } else {
                        $log->info('Theme: bundle includes a design payload, skipped (merge_design opt is off).');
                    }
                }

                // ID-preserving media seed runs before templates/pages so
                // by-reference content resolves against the seeded rows. Gated
                // on import_media (session 309 — default on).
                if (! empty($payload['media']) && is_array($payload['media'])) {
                    if ($opts['import_media']) {
                        $seededMediaIds = $this->importMedia($payload['media'], $log);
                    } else {
                        $log->info('Media: bundle includes a media seed list, skipped (import_media opt is off).');
                    }
                }

                foreach ($payload['templates'] ?? [] as $templateData) {
                    $this->importTemplate($templateData, $log);
                }

                if ($opts['import_pages']) {
                    foreach ($payload['pages'] ?? [] as $pageData) {
                        $this->importPage($pageData, $log);
                    }

                    // Re-link page templates to their chrome pages now that the pages exist.
                    foreach ($payload['templates'] ?? [] as $templateData) {
                        if (($templateData['type'] ?? null) === 'page') {
                            $this->relinkTemplateChrome($templateData, $log);
                        }
                    }
                } elseif (! empty($payload['pages'])) {
                    $log->info('Pages: bundle includes ' . count($payload['pages']) . ' page entries, skipped (import_pages opt is off).');
                }

                // Navigation runs after pages so item.page_slug references
                // resolve against rows the import just landed. Session A001.
                if (! empty($payload['navigation_menus']) && is_array($payload['navigation_menus'])) {
                    if ($opts['import_navigation']) {
                        foreach ($payload['navigation_menus'] as $menuData) {
                            $this->importNavigationMenu($menuData, $log);
                        }
                    } else {
                        $log->info('Navigation: bundle includes ' . count($payload['navigation_menus']) . ' menu entries, skipped (import_navigation opt is off).');
                    }
                }
            });
        } finally {
            $this->mediaRoot             = null;
            $this->replaceDuplicatePages = true;
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

        if ($existing && ! $this->replaceDuplicatePages) {
            $log->info("Page \"{$slug}\": slug already exists on this install, skipped (replace_duplicate_pages opt is off).");

            return;
        }

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

    // ── Navigation (session A001) ──────────────────────────────────────────

    /**
     * Mirrors NavigationMenuResource::saveItems() — the menu is upserted by
     * handle, its items are deleted wholesale, then re-inserted in two passes
     * (roots first to get parent ids, then children). page_slug references
     * resolve against existing Page rows; absent slugs warn and leave page_id
     * null so the link degrades to inert rather than dangling.
     *
     * @param  array<string, mixed>  $data
     */
    protected function importNavigationMenu(array $data, ImportLog $log): void
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
