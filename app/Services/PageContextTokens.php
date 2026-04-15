<?php

namespace App\Services;

use App\Models\Page;
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
     * @return array<string,string>
     */
    public function values(Page $page): array
    {
        $event = $page->event;

        return [
            'title'     => (string) ($page->title ?? ''),
            'date'      => $page->published_at?->format('F j, Y') ?? '',
            'excerpt'   => (string) ($page->meta_description ?? ''),
            'author'    => (string) ($page->author?->name ?? ''),
            'starts_at' => $event?->starts_at?->format('F j, Y g:i a') ?? '',
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
