<?php

namespace App\WidgetPrimitive\Projectors;

use App\Models\Page;
use App\WidgetPrimitive\DataContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SystemModelProjector
{
    /**
     * Project a pre-fetched Eloquent collection of Pages into a row-set DTO.
     *
     * Only fields declared on the contract appear on each row. Only 'post' is
     * wired up in this prototype; other model handles return an empty item set.
     *
     * @param  Collection<int, Page>  $rows
     * @return array{items: array<int, array<string, mixed>>}
     */
    public function project(DataContract $contract, Collection $rows): array
    {
        if ($contract->model !== 'post') {
            return ['items' => []];
        }

        $blogPrefix = config('site.blog_prefix', 'news');

        $projected = $rows->map(function (Page $post) use ($contract, $blogPrefix) {
            $full = $this->projectPost($post, $blogPrefix);

            $row = [];
            foreach ($contract->fields as $field) {
                $row[$field] = $full[$field] ?? '';
            }
            return $row;
        })->values()->all();

        return ['items' => $projected];
    }

    /**
     * Flat row shape derived from a Page model. Each row-field is a value the
     * contract may request; fields outside this map are not exposed.
     *
     * @return array<string, mixed>
     */
    private function projectPost(Page $post, string $blogPrefix): array
    {
        $thumb = $post->getFirstMediaUrl('post_thumbnail', 'webp')
            ?: $post->getFirstMediaUrl('post_thumbnail');

        return [
            'id'       => $post->id,
            'title'    => $post->title,
            'slug'     => $post->slug,
            'url'      => url('/' . $post->slug),
            'date'     => $post->published_at?->format('F j, Y') ?? '',
            'date_iso' => $post->published_at?->toIso8601String() ?? '',
            'excerpt'  => Str::limit(strip_tags($post->meta_description ?? ''), 160),
            'image'    => $thumb,
        ];
    }
}
