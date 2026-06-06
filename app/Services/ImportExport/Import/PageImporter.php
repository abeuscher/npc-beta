<?php

namespace App\Services\ImportExport\Import;

use App\Models\Page;
use App\Models\Template;
use App\Services\ImportExport\ImportLog;
use Illuminate\Support\Facades\Storage;

/**
 * Imports a serialized page: upsert by slug (overwrite in place or skip when
 * the duplicate-replace opt is off), reattach page-level media, then replay the
 * widget tree via {@see WidgetTreeHydrator}. Session 309 duplicate-page gate.
 */
class PageImporter
{
    public function __construct(
        private WidgetTreeHydrator $widgetTree,
        private BundleAuthorResolver $authors,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function import(array $data, ImportLog $log, BundleMediaArchive $archive, bool $replaceDuplicatePages): void
    {
        $slug = $data['slug'] ?? null;
        if (! $slug) {
            $log->warning('Page entry missing slug, skipped.');

            return;
        }

        $existing = Page::withTrashed()->where('slug', $slug)->first();

        if ($existing && ! $replaceDuplicatePages) {
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
            $attributes['author_id'] = $this->authors->resolve();
            if (! empty($data['id'])) {
                $attributes['id'] = $data['id'];
            }
            $page = Page::create($attributes);
        }

        $this->rewirePageMedia($page, $data['media'] ?? [], $log, $archive);
        $this->widgetTree->hydrate($page, $data['widgets'] ?? [], $log, $archive, $page->slug);
    }

    /**
     * Reattach page-level media collections (post_thumbnail, post_header, og_image)
     * from the bundle's media descriptors. Mirrors `WidgetTreeHydrator::rewireWidgetMedia()`.
     *
     * @param  array<int, array<string, mixed>>  $descriptors
     */
    protected function rewirePageMedia(Page $page, array $descriptors, ImportLog $log, BundleMediaArchive $archive): void
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

            $archiveAbs = $archive->archiveFile($path);
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
}
