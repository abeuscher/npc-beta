<?php

namespace App\Services\ImportExport;

use App\Models\Contact;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Organization;
use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\Template;
use App\Models\TicketTier;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ContentExporter
{
    public const FORMAT_VERSION = '1.1.0';

    /**
     * Export one or more pages (by id) into a bundle envelope.
     *
     * @param  array<int, string>  $pageIds
     * @param  array{with_design?: bool, with_media?: bool}  $opts  Opt-in inclusions (session 309). Defaults: no design, no media.
     * @return array<string, mixed>
     */
    public function exportPages(array $pageIds, array $opts = []): array
    {
        $pages   = $this->serializePages($pageIds);
        $payload = ['templates' => [], 'pages' => $pages];

        $this->attachOptIns($payload, $pages, [], $opts);

        return $this->envelope($payload);
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

        $serializedTemplates = $this->serializeTemplates($templates);
        $pages               = $this->serializePages($nestedPageIds);
        $payload             = ['templates' => $serializedTemplates, 'pages' => $pages];

        $this->attachOptIns($payload, $pages, $serializedTemplates, $opts);

        return $this->envelope($payload);
    }

    /**
     * Session 310: full-snapshot wrapper. Enumerates every Page + every
     * Template on the install and emits a single combined envelope. Defaults
     * `with_design` + `with_media` to true so the rollup carries the theme
     * and a referenced-media seed list out of the box; callers can override
     * either flag explicitly. The wrapper delegates assembly to exportBundle();
     * it adds no new payload shape.
     *
     * @param  array{with_design?: bool, with_media?: bool}  $opts
     * @return array<string, mixed>
     */
    public function exportSite(array $opts = []): array
    {
        $opts = array_replace(
            ['with_design' => true, 'with_media' => true],
            $opts,
        );

        $pageIds     = Page::pluck('id')->all();
        $templateIds = Template::pluck('id')->all();

        return $this->exportBundle($pageIds, $templateIds, $opts);
    }

    /**
     * Export a combined bundle of pages and templates.
     *
     * @param  array<int, string>  $pageIds
     * @param  array<int, string>  $templateIds
     * @param  array{with_design?: bool, with_media?: bool}  $opts
     * @return array<string, mixed>
     */
    public function exportBundle(array $pageIds, array $templateIds, array $opts = []): array
    {
        $templates     = Template::whereIn('id', $templateIds)->get();
        $nestedPageIds = $this->collectChromePageIds($templates);
        $allPageIds    = array_values(array_unique(array_merge($pageIds, $nestedPageIds)));

        $serializedTemplates = $this->serializeTemplates($templates);
        $pages               = $this->serializePages($allPageIds);
        $payload             = ['templates' => $serializedTemplates, 'pages' => $pages];

        $this->attachOptIns($payload, $pages, $serializedTemplates, $opts);

        return $this->envelope($payload);
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
            $design = [];
            foreach (['theme_colors', 'typography', 'button_styles'] as $key) {
                $value = SiteSetting::get($key);
                if (is_array($value)) {
                    $design[$key] = $value;
                }
            }
            $payload['design'] = $design;
        }

        if (! empty($opts['with_media'])) {
            $payload['media'] = $this->collectReferencedMedia($serializedPages, $serializedTemplates);
        }
    }

    /**
     * Walk the per-page / per-widget media descriptors already produced by the
     * page serializer and collect every media id they reference, then emit a
     * posture-B descriptor list (same shape exportMedia() emits) so the bundle
     * carries a self-contained media seed that the importer can replay before
     * pages re-attach their by-reference references.
     *
     * @param  array<int, array<string, mixed>>  $serializedPages
     * @param  array<int, array<string, mixed>>  $serializedTemplates
     * @return array<int, array<string, mixed>>
     */
    protected function collectReferencedMedia(array $serializedPages, array $serializedTemplates): array
    {
        $ids = [];

        $walk = function (array $widgets) use (&$ids, &$walk): void {
            foreach ($widgets as $item) {
                if (($item['type'] ?? 'widget') === 'layout') {
                    foreach ($item['slots'] ?? [] as $slotWidgets) {
                        $walk($slotWidgets);
                    }
                    continue;
                }
                foreach ($item['media'] ?? [] as $desc) {
                    $path = $desc['path'] ?? null;
                    if (is_string($path) && preg_match('/^(\d+)\//', $path, $m)) {
                        $ids[] = (int) $m[1];
                    }
                }
            }
        };

        foreach ($serializedPages as $page) {
            foreach ($page['media'] ?? [] as $desc) {
                $path = $desc['path'] ?? null;
                if (is_string($path) && preg_match('/^(\d+)\//', $path, $m)) {
                    $ids[] = (int) $m[1];
                }
            }
            $walk($page['widgets'] ?? []);
        }

        foreach ($serializedTemplates as $tpl) {
            $walk($tpl['widgets'] ?? []);
        }

        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            return [];
        }

        return Media::whereIn('id', $ids)
            ->orderBy('id')
            ->get()
            ->map(fn (Media $m) => $this->serializeMediaRow($m))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function envelope(array $payload): array
    {
        return [
            'format_version' => self::FORMAT_VERSION,
            'exported_at'    => now()->toIso8601String(),
            // Additive, ignorable hint only (media-portability draft decision
            // #1) — never load-bearing for validateEnvelope(). BundleArchive
            // rewrites this to "embedded" when it wraps the envelope in a zip.
            'media_transport' => 'reference',
            'payload'        => $payload,
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
        $design = [];
        foreach (['theme_colors', 'typography', 'button_styles'] as $key) {
            $value = SiteSetting::get($key);
            if (is_array($value)) {
                $design[$key] = $value;
            }
        }

        return $this->envelope(['design' => $design]);
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
            'media' => Media::whereIn('id', $mediaIds)
                ->orderBy('id')
                ->get()
                ->map(fn (Media $m) => $this->serializeMediaRow($m))
                ->all(),
        ]);
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
        $events = $this->collectEventsForExport($eventIds);

        $landingPageIds = $events
            ->pluck('landing_page_id')
            ->filter()
            ->unique()
            ->all();

        $withRegistrations = (bool) ($opts['with_registrations'] ?? false);

        return $this->envelope([
            'pages'  => $this->serializePages($landingPageIds),
            'events' => $events->map(fn (Event $e) => $this->serializeEvent($e, $withRegistrations))->all(),
        ]);
    }

    /**
     * @param  array<int, string>  $eventIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Event>
     */
    protected function collectEventsForExport(array $eventIds): \Illuminate\Database\Eloquent\Collection
    {
        if (empty($eventIds)) {
            return Event::whereRaw('1 = 0')->get();
        }

        return Event::whereIn('id', $eventIds)
            ->with([
                'ticketTiers' => fn ($q) => $q->orderBy('sort_order'),
                'registrations.ticketTier',
                'registrations.contact',
                'registrations.organization',
                'landingPage',
                'sponsorOrganization',
                'media',
            ])
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeEvent(Event $event, bool $withRegistrations): array
    {
        $tierNamesById = $event->ticketTiers->pluck('name', 'id')->all();

        $entry = [
            'event' => [
                'title'                        => $event->title,
                'slug'                         => $event->slug,
                'description'                  => $event->description,
                'status'                       => $event->status,
                'address_line_1'               => $event->address_line_1,
                'address_line_2'               => $event->address_line_2,
                'city'                         => $event->city,
                'state'                        => $event->state,
                'zip'                          => $event->zip,
                'map_url'                      => $event->map_url,
                'map_label'                    => $event->map_label,
                'meeting_url'                  => $event->meeting_url,
                'meeting_label'                => $event->meeting_label,
                'meeting_details'              => $event->meeting_details,
                'registration_mode'            => $event->registration_mode,
                'external_registration_url'    => $event->external_registration_url,
                'auto_create_contacts'         => (bool) $event->auto_create_contacts,
                'mailing_list_opt_in_enabled'  => (bool) $event->mailing_list_opt_in_enabled,
                'custom_fields'                => $event->custom_fields ?? [],
                'starts_at'                    => $event->starts_at?->toIso8601String(),
                'ends_at'                      => $event->ends_at?->toIso8601String(),
                'published_at'                 => $event->published_at?->toIso8601String(),
                'landing_page_slug'            => $event->landingPage?->slug,
                'sponsor_organization_name'    => $event->sponsorOrganization?->name,
            ],
            'tiers' => $event->ticketTiers->map(fn (TicketTier $t) => [
                'name'       => $t->name,
                'price'      => $t->price,
                'capacity'   => $t->capacity,
                'sort_order' => (int) $t->sort_order,
            ])->all(),
            'media' => $this->serializeEventMedia($event),
        ];

        // Registrations key is present iff the operator opted in. Absence on
        // re-import means "leave existing registrations alone"; presence
        // (even when empty) means "registrations are canonical from bundle".
        if ($withRegistrations) {
            $entry['registrations'] = $event->registrations
                ->map(fn (EventRegistration $r) => $this->serializeRegistration($r, $tierNamesById))
                ->all();
        }

        return $entry;
    }

    /**
     * @param  array<string, string>  $tierNamesById
     * @return array<string, mixed>
     */
    protected function serializeRegistration(EventRegistration $r, array $tierNamesById): array
    {
        return [
            'ticket_tier_name'         => $r->ticket_tier_id ? ($tierNamesById[$r->ticket_tier_id] ?? null) : null,
            'quantity'                 => (int) ($r->quantity ?? 1),
            'name'                     => $r->name,
            'email'                    => $r->email,
            'phone'                    => $r->phone,
            'company'                  => $r->company,
            'address_line_1'           => $r->address_line_1,
            'address_line_2'           => $r->address_line_2,
            'city'                     => $r->city,
            'state'                    => $r->state,
            'zip'                      => $r->zip,
            'status'                   => $r->status,
            'registered_at'            => $r->registered_at?->toIso8601String(),
            'notes'                    => $r->notes,
            'mailing_list_opt_in'      => (bool) $r->mailing_list_opt_in,
            'ticket_type'              => $r->ticket_type,
            'ticket_fee'               => $r->ticket_fee,
            'payment_state'            => $r->payment_state,
            'custom_fields'            => $r->custom_fields ?? [],
            'contact_email'            => $r->contact?->email,
            'organization_name'        => $r->organization?->name,
        ];
    }

    /**
     * Mirrors serializePageMedia() for the event media collections.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeEventMedia(Event $event): array
    {
        $descriptors = [];

        foreach (['event_thumbnail', 'event_header', 'event_og_image'] as $collection) {
            $media = $event->getFirstMedia($collection);
            if (! $media) {
                continue;
            }

            $descriptors[] = [
                'collection_name' => $collection,
                'file_name'       => $media->file_name,
                'disk'            => $media->disk,
                'path'            => $media->id . '/' . $media->file_name,
                'mime_type'       => $media->mime_type,
                'size'            => $media->size,
            ];
        }

        return $descriptors;
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
            'products' => $this->serializeProducts($productIds),
        ]);
    }

    /**
     * @param  array<int, string>  $productIds
     * @return array<int, array<string, mixed>>
     */
    protected function serializeProducts(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        return Product::whereIn('id', $productIds)
            ->with(['prices' => fn ($q) => $q->orderBy('sort_order'), 'media'])
            ->get()
            ->map(fn (Product $p) => $this->serializeProduct($p))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeProduct(Product $product): array
    {
        return [
            'product' => [
                'name'         => $product->name,
                'slug'         => $product->slug,
                'description'  => $product->description,
                'capacity'     => $product->capacity,
                'status'       => $product->status,
                'sort_order'   => $product->sort_order,
                'is_archived'  => (bool) $product->is_archived,
                'published_at' => $product->published_at?->toIso8601String(),
            ],
            'prices' => $product->prices->map(fn ($price) => [
                'label'           => $price->label,
                'amount'          => $price->amount,
                'stripe_price_id' => $price->stripe_price_id,
                'sort_order'      => (int) $price->sort_order,
            ])->all(),
            'media' => $this->serializeProductMedia($product),
        ];
    }

    /**
     * Mirrors serializePageMedia() for the product_image single-file collection.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeProductMedia(Product $product): array
    {
        $descriptors = [];

        $media = $product->getFirstMedia('product_image');
        if ($media) {
            $descriptors[] = [
                'collection_name' => 'product_image',
                'file_name'       => $media->file_name,
                'disk'            => $media->disk,
                'path'            => $media->id . '/' . $media->file_name,
                'mime_type'       => $media->mime_type,
                'size'            => $media->size,
            ];
        }

        return $descriptors;
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
            'navigation_menus' => $this->serializeNavigationMenus($menuIds),
        ]);
    }

    /**
     * @param  array<int, string>  $menuIds
     * @return array<int, array<string, mixed>>
     */
    protected function serializeNavigationMenus(array $menuIds): array
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

        $itemsById = $menu->items->keyBy('id');

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

    /**
     * @return array<string, mixed>
     */
    public function exportAllMedia(): array
    {
        return $this->envelope([
            'media' => Media::orderBy('id')
                ->get()
                ->map(fn (Media $m) => $this->serializeMediaRow($m))
                ->all(),
        ]);
    }

    /**
     * Posture-B descriptor: every column needed to recreate the row by raw
     * explicit-id insert on the target, plus the on-disk path so the bytes
     * land at {id}/{file_name} (media-portability draft decision #3).
     *
     * @return array<string, mixed>
     */
    protected function serializeMediaRow(Media $m): array
    {
        return [
            'id'                => $m->id,
            'uuid'              => $m->uuid,
            'model_type'        => $m->model_type,
            'model_id'          => $m->model_id,
            'collection_name'   => $m->collection_name,
            'name'              => $m->name,
            'file_name'         => $m->file_name,
            'mime_type'         => $m->mime_type,
            'disk'              => $m->disk,
            'conversions_disk'  => $m->conversions_disk,
            'size'              => $m->size,
            'manipulations'     => $m->manipulations ?? [],
            'custom_properties' => $m->custom_properties ?? [],
            'responsive_images' => $m->responsive_images ?? [],
            'order_column'      => $m->order_column,
            'path'              => $m->id . '/' . $m->file_name,
        ];
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

    // ── Page serialization ──────────────────────────────────────────────────

    /**
     * @param  array<int, string>  $pageIds
     * @return array<int, array<string, mixed>>
     */
    protected function serializePages(array $pageIds): array
    {
        if (empty($pageIds)) {
            return [];
        }

        return Page::whereIn('id', $pageIds)
            ->with('media')
            ->get()
            ->map(fn (Page $page) => $this->serializePage($page))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializePage(Page $page): array
    {
        $template = $page->template_id ? Template::find($page->template_id) : null;

        return [
            'id'               => $page->id,
            'title'            => $page->title,
            'slug'             => $page->slug,
            'type'             => $page->type,
            'template_name'    => $template?->name,
            'status'           => $page->status,
            'meta_title'       => $page->meta_title,
            'meta_description' => $page->meta_description,
            'noindex'          => $page->noindex,
            'head_snippet'     => $page->head_snippet,
            'body_snippet'     => $page->body_snippet,
            'custom_fields'    => $page->custom_fields ?? [],
            'published_at'     => $page->published_at?->toIso8601String(),
            'media'            => $this->serializePageMedia($page),
            'widgets'          => $this->serializeWidgetTree($page->id),
        ];
    }

    /**
     * Build media descriptors for any single-file collection registered on the
     * Page model that has an attached file.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializePageMedia(Page $page): array
    {
        $descriptors = [];

        foreach (['post_thumbnail', 'post_header', 'og_image'] as $collection) {
            $media = $page->getFirstMedia($collection);
            if (! $media) {
                continue;
            }

            $descriptors[] = [
                'collection_name' => $collection,
                'file_name'       => $media->file_name,
                'disk'            => $media->disk,
                'path'            => $media->id . '/' . $media->file_name,
                'mime_type'       => $media->mime_type,
                'size'            => $media->size,
            ];
        }

        return $descriptors;
    }

    /**
     * Walk a page's widget+layout tree, injecting media descriptors per widget.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeWidgetTree(string $pageId): array
    {
        $page = \App\Models\Page::find($pageId);

        return $page ? $this->serializeWidgetTreeForOwner($page) : [];
    }

    /**
     * Walk any owner's widget+layout tree (Page or Template).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeWidgetTreeForOwner(\Illuminate\Database\Eloquent\Model $owner): array
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
            'config'            => $pw->config ?? [],
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
                'path'            => $media->id . '/' . $media->file_name,
                'mime_type'       => $media->mime_type,
                'size'            => $media->size,
            ];
        }

        return $descriptors;
    }

    // ── Template serialization ──────────────────────────────────────────────

    /**
     * @param  Collection<int, Template>  $templates
     * @return array<int, array<string, mixed>>
     */
    protected function serializeTemplates(Collection $templates): array
    {
        return $templates->map(fn (Template $t) => $this->serializeTemplate($t))->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeTemplate(Template $template): array
    {
        $data = [
            'name'        => $template->name,
            'type'        => $template->type,
            'description' => $template->description,
            'is_default'  => $template->is_default,
        ];

        if ($template->type === 'content') {
            $data['widgets'] = $this->serializeWidgetTreeForOwner($template);

            return $data;
        }

        // Page template — chrome page slug references + custom SCSS.
        // Colour is no longer per-template (session-297 relocation to the
        // site-wide Theme palette); the colour columns were dropped.
        $data['custom_scss']      = $template->custom_scss;
        $data['header_page_slug'] = $template->headerPage?->slug;
        $data['footer_page_slug'] = $template->footerPage?->slug;

        // Session-301 per-template structural deviation. Additive — carried
        // so a template's selected scheme + chrome suppression round-trips
        // (dropping them would silently lose a page's deviation config). The
        // standalone portable-theme feature is a separate post-301 session;
        // this is only the correctness carry-through of the new columns.
        $data['scheme']    = $template->scheme;
        $data['no_header'] = (bool) $template->no_header;
        $data['no_footer'] = (bool) $template->no_footer;

        return $data;
    }
}
