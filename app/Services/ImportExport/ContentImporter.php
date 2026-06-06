<?php

namespace App\Services\ImportExport;

use App\Models\Collection;
use App\Models\Event;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Product;
use App\Models\Template;
use App\Services\AssetBuildService;
use App\Services\ImportExport\Import\BundleMediaArchive;
use App\Services\ImportExport\Import\CollectionImporter;
use App\Services\ImportExport\Import\DesignImporter;
use App\Services\ImportExport\Import\EventImporter;
use App\Services\ImportExport\Import\MediaImporter;
use App\Services\ImportExport\Import\NavigationImporter;
use App\Services\ImportExport\Import\PageImporter;
use App\Services\ImportExport\Import\ProductImporter;
use App\Services\ImportExport\Import\SiteSettingsImporter;
use App\Services\ImportExport\Import\TemplateImporter;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates site-bundle import. `analyze()` is a pure pre-flight inspection
 * the import UI drives; `import()` validates the envelope, opens one transaction
 * and walks the payload sections in dependency order (settings → design → media
 * → templates → pages → navigation → products → events → collections), then runs
 * the post-commit CSS rebuild + conversion regeneration. The per-section write
 * logic is delegated to the collaborators under
 * {@see \App\Services\ImportExport\Import}. Pairs with {@see ContentExporter}.
 */
class ContentImporter
{
    public function __construct(
        private SiteSettingsImporter $siteSettings,
        private DesignImporter $design,
        private MediaImporter $media,
        private TemplateImporter $templates,
        private PageImporter $pages,
        private NavigationImporter $navigation,
        private ProductImporter $products,
        private EventImporter $events,
        private CollectionImporter $collections,
    ) {}

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
            'import_products'         => true,
            'import_events'           => true,
            'import_collections'      => true,
            'import_site_settings'    => false,
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
     *     navigation_menus: array<int, array{handle: string, label: string, items_count: int, exists_locally: bool}>,
     *     products: array<int, array{slug: string, name: string, prices_count: int, exists_locally: bool}>,
     *     events: array<int, array{slug: string, title: string, tiers_count: int, registrations_count: int, exists_locally: bool}>,
     *     collections: array<int, array{handle: string, name: string, items_count: int, exists_locally: bool}>,
     *     site_settings: array{keys: array<int, string>, count: int, blocked_keys: array<int, string>},
     *     manifest: array<string, mixed>|null,
     *     manifest_warnings: array<int, string>
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

        $products     = [];
        $productSlugs = [];
        foreach ($payload['products'] ?? [] as $entry) {
            $slug = $entry['product']['slug'] ?? null;
            $name = $entry['product']['name'] ?? null;
            if (! is_string($slug) || $slug === '' || ! is_string($name)) {
                continue;
            }
            $pricesCount = is_array($entry['prices'] ?? null) ? count($entry['prices']) : 0;
            $products[] = [
                'slug'           => $slug,
                'name'           => $name,
                'prices_count'   => $pricesCount,
                'exists_locally' => false,
            ];
            $productSlugs[] = $slug;
        }
        if (! empty($productSlugs)) {
            $existingProductSlugs = Product::whereIn('slug', $productSlugs)->pluck('slug')->all();
            $existingProductSet   = array_flip($existingProductSlugs);
            foreach ($products as &$row) {
                $row['exists_locally'] = isset($existingProductSet[$row['slug']]);
            }
            unset($row);
        }

        $events     = [];
        $eventSlugs = [];
        foreach ($payload['events'] ?? [] as $entry) {
            $slug  = $entry['event']['slug'] ?? null;
            $title = $entry['event']['title'] ?? null;
            if (! is_string($slug) || $slug === '' || ! is_string($title)) {
                continue;
            }
            $tiersCount         = is_array($entry['tiers'] ?? null) ? count($entry['tiers']) : 0;
            $registrationsCount = is_array($entry['registrations'] ?? null) ? count($entry['registrations']) : 0;
            $events[] = [
                'slug'                => $slug,
                'title'               => $title,
                'tiers_count'         => $tiersCount,
                'registrations_count' => $registrationsCount,
                'exists_locally'      => false,
            ];
            $eventSlugs[] = $slug;
        }
        if (! empty($eventSlugs)) {
            $existingEventSlugs = Event::whereIn('slug', $eventSlugs)->pluck('slug')->all();
            $existingEventSet   = array_flip($existingEventSlugs);
            foreach ($events as &$row) {
                $row['exists_locally'] = isset($existingEventSet[$row['slug']]);
            }
            unset($row);
        }

        $collections        = [];
        $collectionHandles  = [];
        foreach ($payload['collections'] ?? [] as $entry) {
            $handle = $entry['collection']['handle'] ?? null;
            $name   = $entry['collection']['name'] ?? null;
            if (! is_string($handle) || $handle === '' || ! is_string($name)) {
                continue;
            }
            $itemsCount = is_array($entry['items'] ?? null) ? count($entry['items']) : 0;
            $collections[] = [
                'handle'         => $handle,
                'name'           => $name,
                'items_count'    => $itemsCount,
                'exists_locally' => false,
            ];
            $collectionHandles[] = $handle;
        }
        if (! empty($collectionHandles)) {
            $existingCollHandles = Collection::whereIn('handle', $collectionHandles)
                ->pluck('handle')
                ->all();
            $existingCollSet = array_flip($existingCollHandles);
            foreach ($collections as &$row) {
                $row['exists_locally'] = isset($existingCollSet[$row['handle']]);
            }
            unset($row);
        }

        $siteSettings = is_array($payload['site_settings'] ?? null) ? $payload['site_settings'] : [];
        $ssKeys       = [];
        $ssBlocked    = [];
        foreach ($siteSettings as $k => $entry) {
            if (! is_string($k) || $k === '') {
                continue;
            }
            if (SiteSettingsBundlePolicy::isDenied($k)) {
                $ssBlocked[] = $k;
            } else {
                $ssKeys[] = $k;
            }
        }
        $siteSettingsSummary = [
            'keys'         => $ssKeys,
            'count'        => count($ssKeys),
            'blocked_keys' => $ssBlocked,
        ];

        $manifest         = is_array($bundle['manifest'] ?? null) ? $bundle['manifest'] : null;
        $manifestWarnings = $manifest === null ? [] : $this->verifyManifest($manifest, $payload);

        return [
            'has_design'        => $designKeys !== [],
            'design_keys'       => $designKeys,
            'has_media'         => $hasMedia,
            'media_count'       => $mediaCount,
            'pages'             => $pages,
            'templates'         => $templates,
            'navigation_menus'  => $navigationMenus,
            'products'          => $products,
            'events'            => $events,
            'collections'       => $collections,
            'site_settings'     => $siteSettingsSummary,
            'manifest'          => $manifest,
            'manifest_warnings' => $manifestWarnings,
        ];
    }

    /**
     * Cross-check a bundle's manifest sections against payload reality. Returns
     * a list of human-readable warnings; an empty list means everything lines
     * up. Manifest is documentation + integrity, never a security gate, so
     * mismatches are warnings (not exceptions). Session A001/3.
     *
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected function verifyManifest(array $manifest, array $payload): array
    {
        $warnings = [];

        $sections = is_array($manifest['sections'] ?? null) ? $manifest['sections'] : [];
        foreach ($sections as $section) {
            $key           = $section['key']   ?? null;
            $declaredCount = $section['count'] ?? null;
            if (! is_string($key) || ! is_int($declaredCount)) {
                continue;
            }
            $actual = is_array($payload[$key] ?? null) ? count($payload[$key]) : 0;
            if ($actual !== $declaredCount) {
                $warnings[] = "Manifest section '{$key}' declared {$declaredCount} items, payload has {$actual}.";
            }
        }

        // Stray sections in payload that manifest doesn't declare are
        // tolerated (a downstream importer might add new section types the
        // manifest spec doesn't yet name). Only count mismatch is flagged.

        return $warnings;
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

        $archive               = new BundleMediaArchive($mediaRoot);
        $replaceDuplicatePages = (bool) $opts['replace_duplicate_pages'];
        $designImported        = false;
        $seededMediaIds        = [];

        // Manifest cross-check (session A001/3). If the bundle carries a
        // manifest, log any declared-vs-actual count mismatches before the
        // transaction opens. Mismatches are warnings, not errors — manifest is
        // documentation, not a security gate.
        if (is_array($bundle['manifest'] ?? null)) {
            foreach ($this->verifyManifest($bundle['manifest'], $bundle['payload'] ?? []) as $msg) {
                $log->warning($msg);
            }
        }

        DB::transaction(function () use ($bundle, $log, $opts, $archive, $replaceDuplicatePages, &$designImported, &$seededMediaIds) {
            $payload = $bundle['payload'] ?? [];

            // SiteSettings runs ahead of design/templates/pages so any
            // downstream pass that reads SiteSetting::get() sees the
            // imported values. Gated on import_site_settings (session
            // A001/3 — default OFF; same posture as merge_design,
            // SiteSettings carry brand/identity values an operator may
            // not want overwritten by an inbound bundle).
            if (! empty($payload['site_settings']) && is_array($payload['site_settings'])) {
                if ($opts['import_site_settings']) {
                    $this->siteSettings->import($payload['site_settings'], $log);
                } else {
                    $log->info('SiteSettings: bundle includes ' . count($payload['site_settings']) . ' setting entries, skipped (import_site_settings opt is off).');
                }
            }

            // Theme/design runs first so a combined bundle establishes the
            // theme before templates/pages resolve against it. Gated on
            // merge_design (session 309 — default off).
            if (! empty($payload['design']) && is_array($payload['design'])) {
                if ($opts['merge_design']) {
                    $this->design->import($payload['design'], $log);
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
                    $seededMediaIds = $this->media->import($payload['media'], $log, $archive);
                } else {
                    $log->info('Media: bundle includes a media seed list, skipped (import_media opt is off).');
                }
            }

            foreach ($payload['templates'] ?? [] as $templateData) {
                $this->templates->import($templateData, $log, $archive);
            }

            if ($opts['import_pages']) {
                foreach ($payload['pages'] ?? [] as $pageData) {
                    $this->pages->import($pageData, $log, $archive, $replaceDuplicatePages);
                }

                // Re-link page templates to their chrome pages now that the pages exist.
                foreach ($payload['templates'] ?? [] as $templateData) {
                    if (($templateData['type'] ?? null) === 'page') {
                        $this->templates->relinkChrome($templateData, $log);
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
                        $this->navigation->import($menuData, $log);
                    }
                } else {
                    $log->info('Navigation: bundle includes ' . count($payload['navigation_menus']) . ' menu entries, skipped (import_navigation opt is off).');
                }
            }

            // Nav-widget config rewiring post-pass (session A001/4).
            // Runs unconditionally if pages were imported: any nav widget
            // on a freshly-imported page whose config carries
            // `navigation_menu_handle` needs final reconciliation against
            // the menus that actually exist on the target install (the
            // bundle may have menus we just imported, or the target may
            // already have them, or neither — the post-pass handles all
            // three cases including the warn-and-null path for stale
            // references with no matching menu anywhere).
            if ($opts['import_pages']) {
                $this->navigation->relinkNavWidgetMenus($log);
            }

            if (! empty($payload['products']) && is_array($payload['products'])) {
                if ($opts['import_products']) {
                    foreach ($payload['products'] as $productData) {
                        $this->products->import($productData, $log, $archive);
                    }
                } else {
                    $log->info('Products: bundle includes ' . count($payload['products']) . ' product entries, skipped (import_products opt is off).');
                }
            }

            // Events run after pages so landing_page_slug resolves
            // against the pages we just imported. Session A001.
            if (! empty($payload['events']) && is_array($payload['events'])) {
                if ($opts['import_events']) {
                    foreach ($payload['events'] as $eventData) {
                        $this->events->import($eventData, $log, $archive);
                    }
                } else {
                    $log->info('Events: bundle includes ' . count($payload['events']) . ' event entries, skipped (import_events opt is off).');
                }
            }

            if (! empty($payload['collections']) && is_array($payload['collections'])) {
                if ($opts['import_collections']) {
                    foreach ($payload['collections'] as $collectionData) {
                        $this->collections->import($collectionData, $log);
                    }
                } else {
                    $log->info('Collections: bundle includes ' . count($payload['collections']) . ' collection entries, skipped (import_collections opt is off).');
                }
            }
        });

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
}
