<?php

namespace App\Services\ImportExport;

use App\Models\Collection as CollectionModel;
use App\Models\Event;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Product;
use App\Models\Template;
use App\Services\ImportExport\Export\CollectionSerializer;
use App\Services\ImportExport\Export\DesignSerializer;
use App\Services\ImportExport\Export\EventSerializer;
use App\Services\ImportExport\Export\MediaSerializer;
use App\Services\ImportExport\Export\NavigationSerializer;
use App\Services\ImportExport\Export\PageSerializer;
use App\Services\ImportExport\Export\ProductSerializer;
use App\Services\ImportExport\Export\SiteSettingsSerializer;
use App\Services\ImportExport\Export\TemplateSerializer;
use Illuminate\Support\Collection;

/**
 * Orchestrates site-bundle export. Each public `exportX()` entry point assembles
 * a payload and wraps it in the versioned envelope (format_version + manifest);
 * the per-entity serialization is delegated to the collaborators under
 * {@see \App\Services\ImportExport\Export}. Pairs with {@see ContentImporter}.
 */
class ContentExporter
{
    public const FORMAT_VERSION = '1.1.0';

    public function __construct(
        private PageSerializer $pages,
        private TemplateSerializer $templates,
        private EventSerializer $events,
        private ProductSerializer $products,
        private NavigationSerializer $navigation,
        private CollectionSerializer $collections,
        private MediaSerializer $media,
        private DesignSerializer $design,
        private SiteSettingsSerializer $siteSettings,
    ) {}

    /**
     * Export one or more pages (by id) into a bundle envelope.
     *
     * @param  array<int, string>  $pageIds
     * @param  array{with_design?: bool, with_media?: bool}  $opts  Opt-in inclusions (session 309). Defaults: no design, no media.
     * @return array<string, mixed>
     */
    public function exportPages(array $pageIds, array $opts = []): array
    {
        $pages   = $this->pages->serializeMany($pageIds);
        $payload = ['templates' => [], 'pages' => $pages];

        $this->attachOptIns($payload, $pages, [], $opts);

        return $this->envelope($payload, 'exportPages');
    }

    /**
     * Export one or more templates (by id) into a bundle envelope.
     * Page templates pull in their associated header/footer system pages.
     *
     * @param  array<int, string>  $templateIds
     * @param  array{with_design?: bool, with_media?: bool}  $opts
     * @return array<string, mixed>
     */
    public function exportTemplates(array $templateIds, array $opts = []): array
    {
        $templates     = Template::whereIn('id', $templateIds)->get();
        $nestedPageIds = $this->collectChromePageIds($templates);

        $serializedTemplates = $this->templates->serializeMany($templates);
        $pages               = $this->pages->serializeMany($nestedPageIds);
        $payload             = ['templates' => $serializedTemplates, 'pages' => $pages];

        $this->attachOptIns($payload, $pages, $serializedTemplates, $opts);

        return $this->envelope($payload, 'exportTemplates');
    }

    /**
     * Session 310: full-snapshot wrapper. Enumerates every Page + every
     * Template on the install and emits a single combined envelope. Defaults
     * `with_design` + `with_media` to true so the rollup carries the theme
     * and a referenced-media seed list out of the box; callers can override
     * either flag explicitly.
     *
     * Session A001/4 extension: the rollup now also enumerates every
     * navigation menu, product, event, and collection on the install so a
     * full-site bundle is actually full-site. Events are exported without
     * registrations by default (`with_registrations` opt, default false —
     * registrations are operator data, not template content; the per-entity
     * `exportEvents()` flag carries the same semantics).
     *
     * @param  array{with_design?: bool, with_media?: bool, with_registrations?: bool}  $opts
     * @return array<string, mixed>
     */
    public function exportSite(array $opts = []): array
    {
        $opts = array_replace(
            ['with_design' => true, 'with_media' => true, 'with_registrations' => false],
            $opts,
        );

        $pageIds       = Page::pluck('id')->all();
        $templateIds   = Template::pluck('id')->all();
        $navMenuIds    = NavigationMenu::pluck('id')->all();
        $productIds    = Product::pluck('id')->all();
        $eventIds      = Event::pluck('id')->all();
        $collectionIds = CollectionModel::pluck('id')->all();

        $templates     = Template::whereIn('id', $templateIds)->get();
        $nestedPageIds = $this->collectChromePageIds($templates);
        $allPageIds    = array_values(array_unique(array_merge($pageIds, $nestedPageIds)));

        $serializedTemplates = $this->templates->serializeMany($templates);
        $pages               = $this->pages->serializeMany($allPageIds);

        $payload = [
            'templates'        => $serializedTemplates,
            'pages'            => $pages,
            'navigation_menus' => $this->navigation->serializeMany($navMenuIds),
            'products'         => $this->products->serializeMany($productIds),
            'collections'      => $this->collections->serializeMany($collectionIds),
        ];

        if (! empty($eventIds)) {
            $events = $this->events->collectForExport($eventIds);
            $payload['events'] = $events
                ->map(fn (Event $e) => $this->events->serialize($e, (bool) $opts['with_registrations']))
                ->all();
        } else {
            $payload['events'] = [];
        }

        $this->attachOptIns($payload, $pages, $serializedTemplates, $opts);

        return $this->envelope($payload, 'exportSite');
    }

    /**
     * Export a combined bundle of pages and templates.
     *
     * @param  array<int, string>  $pageIds
     * @param  array<int, string>  $templateIds
     * @param  array{with_design?: bool, with_media?: bool}  $opts
     * @param  string|null  $sourceActionOverride  Wrappers like exportSite()
     *                                              delegate here but want
     *                                              their own source-action
     *                                              stamped in the manifest.
     * @return array<string, mixed>
     */
    public function exportBundle(array $pageIds, array $templateIds, array $opts = [], ?string $sourceActionOverride = null): array
    {
        $templates     = Template::whereIn('id', $templateIds)->get();
        $nestedPageIds = $this->collectChromePageIds($templates);
        $allPageIds    = array_values(array_unique(array_merge($pageIds, $nestedPageIds)));

        $serializedTemplates = $this->templates->serializeMany($templates);
        $pages               = $this->pages->serializeMany($allPageIds);
        $payload             = ['templates' => $serializedTemplates, 'pages' => $pages];

        $this->attachOptIns($payload, $pages, $serializedTemplates, $opts);

        return $this->envelope($payload, $sourceActionOverride ?? 'exportBundle');
    }

    /**
     * Session 309 opt-ins. Mutates the payload to attach payload.design and/or
     * payload.media when explicitly requested. Both default off so a page-or-
     * template bundle no longer silently carries theme or a media seed list.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>  $serializedPages
     * @param  array<int, array<string, mixed>>  $serializedTemplates
     * @param  array{with_design?: bool, with_media?: bool}  $opts
     */
    protected function attachOptIns(array &$payload, array $serializedPages, array $serializedTemplates, array $opts): void
    {
        if (! empty($opts['with_design'])) {
            $payload['design'] = $this->design->collect();
        }

        if (! empty($opts['with_media'])) {
            $payload['media'] = $this->media->collectReferenced($serializedPages, $serializedTemplates);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string|null  $sourceAction  Name of the exporter method emitting
     *                                     this envelope (e.g. 'exportPages').
     *                                     Stamped into the manifest for
     *                                     provenance + debugging. Session A001/3.
     * @param  array<string, mixed>  $policyHints  Optional per-section import
     *                                              defaults the exporter wants
     *                                              to suggest. Treated by the
     *                                              importer as UX hint only,
     *                                              never as a security gate.
     * @return array<string, mixed>
     */
    protected function envelope(array $payload, ?string $sourceAction = null, array $policyHints = []): array
    {
        return [
            'format_version' => self::FORMAT_VERSION,
            'exported_at'    => now()->toIso8601String(),
            // Additive, ignorable hint only (media-portability draft decision
            // #1) — never load-bearing for validateEnvelope(). BundleArchive
            // rewrites this to "embedded" when it wraps the envelope in a zip.
            'media_transport' => 'reference',
            // Session A001/3 manifest layer. Declarative top-level block that
            // names each payload section and its count for UI/integrity. Old
            // bundles without this block remain importable (the importer falls
            // back to payload-key sniffing). The manifest is documentation +
            // integrity, never the security boundary — opt-flags + deny-lists
            // stay on the importer side.
            'manifest'       => $this->buildManifest($payload, $sourceAction, $policyHints),
            'payload'        => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $policyHints
     * @return array<string, mixed>
     */
    protected function buildManifest(array $payload, ?string $sourceAction, array $policyHints): array
    {
        $sections = [];
        foreach ($payload as $key => $value) {
            if (! is_array($value)) {
                continue;
            }
            $sections[] = [
                'key'   => $key,
                'count' => count($value),
            ];
        }

        return [
            'sections'         => $sections,
            'policy_hints'     => (object) $policyHints,
            'exported_with'    => $sourceAction,
            'exporter_version' => self::FORMAT_VERSION,
        ];
    }

    /**
     * Export the site-wide theme/design as an additive payload.design — the
     * three design-group SiteSetting rows captured exactly as stored. Import
     * deep-merges these over resolver defaults and never sweeps (session 303,
     * the 295 em-rhythm-revert lesson / concrete-values rule). A theme bundle
     * carries no media, so it flows through the same envelope as a tiny JSON.
     *
     * @return array<string, mixed>
     */
    public function exportDesign(): array
    {
        return $this->envelope(['design' => $this->design->collect()], 'exportDesign');
    }

    /**
     * Export the curated visual/SEO/routing slice of SiteSettings as
     * `payload.site_settings`. Source of truth for what travels is
     * {@see SiteSettingsBundlePolicy::ALLOW_LIST}; the deny-list is applied
     * defensively so secrets can never enter the bundle even if a key
     * accidentally appears on both lists. Encrypted-type rows are hard-skipped.
     * Session A001/3.
     *
     * @return array<string, mixed>
     */
    public function exportSiteSettings(): array
    {
        return $this->envelope(
            ['site_settings' => $this->siteSettings->collect()],
            'exportSiteSettings',
            ['allow_list' => SiteSettingsBundlePolicy::ALLOW_LIST],
        );
    }

    /**
     * Standalone ID-preserving media seed (media-portability draft decision
     * #6/#3). payload.media is a flat list of full posture-B descriptors;
     * pages/templates stay empty so a combined bundle seeds media first.
     *
     * @param  array<int, int|string>  $mediaIds
     * @return array<string, mixed>
     */
    public function exportMedia(array $mediaIds): array
    {
        return $this->envelope([
            'media' => $this->media->serializeIds($mediaIds),
        ], 'exportMedia');
    }

    /**
     * @return array<string, mixed>
     */
    public function exportAllMedia(): array
    {
        return $this->envelope([
            'media' => $this->media->serializeAll(),
        ], 'exportAllMedia');
    }

    /**
     * Export one or more collections (by id) into a bundle envelope. Each
     * entry carries the collection shell + its full item list. Session A001.
     *
     * @param  array<int, string>  $collectionIds
     * @return array<string, mixed>
     */
    public function exportCollections(array $collectionIds): array
    {
        return $this->envelope([
            'collections' => $this->collections->serializeMany($collectionIds),
        ], 'exportCollections');
    }

    /**
     * Export one or more events (by id) into a bundle envelope. Each entry
     * carries the event row, its tiers, and (optionally) its registrations.
     * Landing pages travel as full page entries in payload.pages so the
     * landing_page_slug back-reference resolves on import. Registrations are
     * opt-in via with_registrations because they are operator data, not
     * template content. Session A001.
     *
     * @param  array<int, string>  $eventIds
     * @param  array{with_registrations?: bool}  $opts
     * @return array<string, mixed>
     */
    public function exportEvents(array $eventIds, array $opts = []): array
    {
        $events = $this->events->collectForExport($eventIds);

        $landingPageIds = $events
            ->pluck('landing_page_id')
            ->filter()
            ->unique()
            ->all();

        $withRegistrations = (bool) ($opts['with_registrations'] ?? false);

        return $this->envelope([
            'pages'  => $this->pages->serializeMany($landingPageIds),
            'events' => $events->map(fn (Event $e) => $this->events->serialize($e, $withRegistrations))->all(),
        ], 'exportEvents');
    }

    /**
     * Export one or more products (by id) into a bundle envelope. Each entry
     * carries the product row, its full price list, and the single
     * `product_image` media descriptor when present. Session A001.
     *
     * @param  array<int, string>  $productIds
     * @return array<string, mixed>
     */
    public function exportProducts(array $productIds): array
    {
        return $this->envelope([
            'products' => $this->products->serializeMany($productIds),
        ], 'exportProducts');
    }

    /**
     * Export one or more navigation menus by id. Each entry carries the menu
     * shell + its full item tree (2-deep, matching the editor). Page
     * references travel as page_slug so re-import resolves against the
     * destination's page rows. Session A001.
     *
     * @param  array<int, string>  $menuIds
     * @return array<string, mixed>
     */
    public function exportNavigation(array $menuIds): array
    {
        return $this->envelope([
            'navigation_menus' => $this->navigation->serializeMany($menuIds),
        ], 'exportNavigation');
    }

    /**
     * @param  Collection<int, Template>  $templates
     * @return array<int, string>
     */
    protected function collectChromePageIds(Collection $templates): array
    {
        $ids = [];
        foreach ($templates as $template) {
            if ($template->type !== 'page') {
                continue;
            }
            if ($template->header_page_id) {
                $ids[] = $template->header_page_id;
            }
            if ($template->footer_page_id) {
                $ids[] = $template->footer_page_id;
            }
        }

        return array_values(array_unique($ids));
    }
}
