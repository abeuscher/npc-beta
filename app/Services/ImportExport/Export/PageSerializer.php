<?php

namespace App\Services\ImportExport\Export;

use App\Models\Page;
use App\Models\Template;

/**
 * Serializes Page rows (and their page-level media) into the bundle's portable
 * page shape. Delegates the widget+layout tree to {@see WidgetTreeSerializer}.
 */
class PageSerializer
{
    public function __construct(private WidgetTreeSerializer $widgetTree) {}

    /**
     * @param  array<int, string>  $pageIds
     * @return array<int, array<string, mixed>>
     */
    public function serializeMany(array $pageIds): array
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
            'widgets'          => $this->widgetTree->forOwner($page),
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
                'id'              => $media->id,
                'path'            => $media->getPathRelativeToRoot(),
                'mime_type'       => $media->mime_type,
                'size'            => $media->size,
            ];
        }

        return $descriptors;
    }
}
