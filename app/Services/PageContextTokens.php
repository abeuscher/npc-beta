<?php

namespace App\Services;

use App\Models\Page;
use App\Support\DateFormat;
use Illuminate\Support\Str;

class PageContextTokens
{
    /**
     * Supported token keys. Anything outside this list passes through unchanged.
     */
    public const TOKENS = [
        'title',
        'date',
        'excerpt',
        'author',
        'starts_at',
        'location',
    ];

    /**
     * Per-page-id memoization of computed token values. Populated on first
     * values() call for a given page id and reused for subsequent calls in
     * the same request. Bound as a container singleton so every widget on a
     * page shares the cache.
     *
     * @var array<int|string, array<string,string>>
     */
    private array $cache = [];

    public function substitute(string $text, ?Page $currentPage, bool $escapeHtml = false): string
    {
        if ($text === '' || $currentPage === null) {
            return $text;
        }

        if (! Str::contains($text, '{{')) {
            return $text;
        }

        $values = $this->values($currentPage);

        foreach ($values as $token => $value) {
            $replacement = $escapeHtml ? e($value) : $value;
            $text = str_replace('{{' . $token . '}}', $replacement, $text);
        }

        return $text;
    }

    /**
     * Resolve every supported token into a concrete string. Missing data yields
     * an empty string; unknown tokens are never injected by this method.
     *
     * Memoized per request by page id — twelve widgets on one page compute
     * token values once, not twelve times. Pages without an id (unsaved,
     * admin preview) fall back to object identity.
     *
     * @return array<string,string>
     */
    public function values(Page $page): array
    {
        $key = $page->id !== null ? 'id:' . $page->id : 'obj:' . spl_object_id($page);

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $event = $page->event;

        return $this->cache[$key] = [
            'title'     => (string) ($page->title ?? ''),
            'date'      => DateFormat::format($page->published_at, DateFormat::LONG_DATE),
            'excerpt'   => (string) ($page->meta_description ?? ''),
            'author'    => (string) ($page->author?->name ?? ''),
            'starts_at' => DateFormat::format($event?->starts_at, DateFormat::LONG_DATETIME),
            'location'  => $this->composeLocation($event),
        ];
    }

    private function composeLocation(?object $event): string
    {
        if ($event === null) {
            return '';
        }

        foreach (['map_label', 'meeting_label'] as $key) {
            $value = trim((string) ($event->{$key} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $city  = trim((string) ($event->city ?? ''));
        $state = trim((string) ($event->state ?? ''));
        if ($city !== '' && $state !== '') {
            return $city . ', ' . $state;
        }
        if ($city !== '') {
            return $city;
        }

        $addr = trim((string) ($event->address_line_1 ?? ''));
        return $addr;
    }
}
