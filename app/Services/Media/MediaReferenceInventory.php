<?php

namespace App\Services\Media;

use App\Models\CollectionItem;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\PageWidget;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Authoritative inventory of where a media row can be referenced in this app.
 *
 * Media is attached to owner models through Spatie collection ownership
 * (model_type + model_id + collection_name); there are no media-id columns in
 * config JSON. A media row is referenced when (A) its owner exists and its
 * collection_name is one the owner currently reads, or (B) its canonical
 * /storage/{id}/ URL is embedded in rich-text content somewhere. Anything the
 * inventory does not recognise is an unused candidate.
 */
class MediaReferenceInventory
{
    public const CLASS_LIVE = 'live';
    public const CLASS_DEAD_COLLECTION = 'dead_collection';
    public const CLASS_ORPHAN_OWNER = 'orphan_owner';

    /**
     * Owner morph class => the fixed set of collection names that owner reads.
     *
     * @var array<class-string, array<int, string>>
     */
    private const FIXED_LIVE_COLLECTIONS = [
        \App\Models\Page::class       => ['post_thumbnail', 'post_header', 'og_image'],
        \App\Models\Event::class      => ['event_thumbnail', 'event_header', 'event_og_image'],
        \App\Models\Product::class    => ['product_image'],
        \App\Models\EmailTemplate::class => ['header_image'],
        \App\Models\WidgetType::class => ['thumbnail', 'thumbnail_hover'],
    ];

    /**
     * Owner morph classes whose collection naming is dynamic / consumer-driven
     * (CollectionItem collections are named by the consuming widget; SampleImage
     * is fixture library data). Any media owned by a live instance of these is
     * treated as referenced — the conservative choice that avoids false-positive
     * "unused" flags.
     *
     * @var array<int, class-string>
     */
    private const ALL_COLLECTIONS_LIVE_OWNERS = [
        \App\Models\CollectionItem::class,
        \App\Models\SampleImage::class,
    ];

    /**
     * Rich-text / JSON surfaces whose raw content may embed a /storage/{id}/ URL.
     * Each entry is [table, column].
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const EMBEDDED_SURFACES = [
        ['page_widgets', 'config'],
        ['collection_items', 'data'],
        ['events', 'description'],
        ['events', 'meeting_details'],
        ['email_templates', 'body'],
    ];

    /** @var array<int, true>|null */
    private ?array $embeddedIdsCache = null;

    /** @var array<int, array<string|int, bool>> */
    private array $ownerExistenceCache = [];

    /** @var array<string, array<int, string>> */
    private array $widgetLiveCollectionsCache = [];

    /**
     * The union of every referenced media id (rules A + B).
     *
     * @return array<int, int>
     */
    public function referencedMediaIds(): array
    {
        $ids = [];

        foreach (Media::query()->cursor() as $media) {
            if ($this->classify($media) === self::CLASS_LIVE) {
                $ids[] = (int) $media->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Classify a single media row: live (referenced), dead_collection (owner
     * exists but no longer reads this collection and the file is not embedded),
     * or orphan_owner (owner row gone — the nightly media-library:clean target).
     */
    public function classify(Media $media): string
    {
        if ($this->isEmbedded((int) $media->id)) {
            return self::CLASS_LIVE;
        }

        $liveCollections = $this->liveCollectionsForOwner($media->model_type, $media->model_id);

        if ($liveCollections === null) {
            return self::CLASS_ORPHAN_OWNER;
        }

        if ($liveCollections === ['*'] || in_array($media->collection_name, $liveCollections, true)) {
            return self::CLASS_LIVE;
        }

        return self::CLASS_DEAD_COLLECTION;
    }

    /**
     * Live collection names for a given owner, or null when the owner row is
     * absent. ['*'] means "every collection on this owner is live".
     *
     * @return array<int, string>|null
     */
    private function liveCollectionsForOwner(?string $modelType, string|int|null $modelId): ?array
    {
        if ($modelType === null || $modelId === null) {
            return null;
        }

        if (! $this->ownerExists($modelType, $modelId)) {
            return null;
        }

        if (in_array($modelType, self::ALL_COLLECTIONS_LIVE_OWNERS, true)) {
            return ['*'];
        }

        if (isset(self::FIXED_LIVE_COLLECTIONS[$modelType])) {
            return self::FIXED_LIVE_COLLECTIONS[$modelType];
        }

        if ($modelType === PageWidget::class) {
            return $this->pageWidgetLiveCollections((string) $modelId);
        }

        // Unknown owner type with no declared live collections: treat the owned
        // media as live to stay conservative (no false-positive unused flags).
        return ['*'];
    }

    /**
     * appearance_background_image plus config_{key} for each image/video field
     * currently in the owning widget type's config schema. inline-images is
     * deliberately absent — those are kept alive by the embedded-URL rule only.
     *
     * @return array<int, string>
     */
    private function pageWidgetLiveCollections(string $widgetId): array
    {
        if (isset($this->widgetLiveCollectionsCache[$widgetId])) {
            return $this->widgetLiveCollectionsCache[$widgetId];
        }

        $collections = ['appearance_background_image'];

        $widget = PageWidget::with('widgetType')->find($widgetId);
        foreach ($widget?->widgetType?->config_schema ?? [] as $field) {
            if (in_array($field['type'] ?? '', ['image', 'video'], true) && ! empty($field['key'])) {
                $collections[] = 'config_' . $field['key'];
            }
        }

        return $this->widgetLiveCollectionsCache[$widgetId] = $collections;
    }

    private function ownerExists(string $modelType, string|int $modelId): bool
    {
        if (! class_exists($modelType)) {
            return false;
        }

        if (! isset($this->ownerExistenceCache[$modelType])) {
            $table = (new $modelType)->getTable();
            $key = (new $modelType)->getKeyName();
            $this->ownerExistenceCache[$modelType] = DB::table($table)
                ->pluck($key)
                ->flip()
                ->map(fn () => true)
                ->all();
        }

        return isset($this->ownerExistenceCache[$modelType][$modelId]);
    }

    private function isEmbedded(int $mediaId): bool
    {
        return isset($this->embeddedMediaIds()[$mediaId]);
    }

    /**
     * Every media id referenced by an embedded /storage/ URL in a rich-text
     * surface. Computed once per instance.
     *
     * Content-addressed URLs (/storage/cas/{shard}/{hash}/) identify content by
     * hash, so an embedded hash marks every media row carrying that hash as
     * referenced — conservative (it never falsely flags an embedded asset
     * unused). Legacy /storage/{id}/ tokens are still resolved directly, so the
     * inventory is correct before and after the content-addressed relocation.
     *
     * @return array<int, true>
     */
    public function embeddedMediaIds(): array
    {
        if ($this->embeddedIdsCache !== null) {
            return $this->embeddedIdsCache;
        }

        $ids = [];
        $hashes = [];

        foreach (self::EMBEDDED_SURFACES as [$table, $column]) {
            foreach (DB::table($table)->pluck($column) as $content) {
                $content = (string) $content;

                foreach ($this->extractStorageIds($content) as $id) {
                    $ids[$id] = true;
                }

                foreach ($this->extractStorageHashes($content) as $hash) {
                    $hashes[$hash] = true;
                }
            }
        }

        if (! empty($hashes)) {
            foreach (Media::query()->whereIn('content_hash', array_keys($hashes))->pluck('id') as $id) {
                $ids[(int) $id] = true;
            }
        }

        return $this->embeddedIdsCache = $ids;
    }

    /**
     * Pull the media id out of every legacy /storage/{id}/ token in a blob of
     * content. Matches img src, anchor href, srcset, and conversion paths alike.
     *
     * @return array<int, int>
     */
    public function extractStorageIds(string $content): array
    {
        if ($content === '' || ! str_contains($content, '/storage/')) {
            return [];
        }

        preg_match_all('#/storage/(\d+)/#', $content, $matches);

        return array_map('intval', $matches[1] ?? []);
    }

    /**
     * Pull the content hash out of every content-addressed /storage/cas/ token.
     *
     * @return array<int, string>
     */
    public function extractStorageHashes(string $content): array
    {
        if ($content === '' || ! str_contains($content, '/storage/cas/')) {
            return [];
        }

        preg_match_all('#/storage/cas/[0-9a-f]{2}/([0-9a-f]{64})/#', $content, $matches);

        return $matches[1] ?? [];
    }
}
