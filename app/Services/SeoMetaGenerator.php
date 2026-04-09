<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

class SeoMetaGenerator
{
    /**
     * Generate all SEO meta data for a given page.
     *
     * @return array{title: string, description: string, og_image: string, canonical: string, og_type: string, json_ld: string|null}
     */
    public static function forPage(Page $page): array
    {
        $baseUrl   = rtrim(SiteSetting::get('base_url', config('app.url')), '/');
        $siteName  = SiteSetting::get('site_name', config('site.name', config('app.name')));
        $canonical = $page->slug === 'home' ? $baseUrl : $baseUrl . '/' . $page->slug;

        $title       = $page->meta_title ?: $page->title;
        $description = $page->meta_description ?: static::extractDescription($page);
        $ogImage     = static::resolveOgImage($page);
        $ogType      = $page->type === 'post' ? 'article' : 'website';

        $jsonLd = static::generateJsonLd($page, $title, $description, $ogImage, $canonical, $siteName);

        return [
            'title'       => $title,
            'description' => $description,
            'og_image'    => $ogImage,
            'canonical'   => $canonical,
            'og_type'     => $ogType,
            'json_ld'     => $jsonLd ? json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        ];
    }

    /**
     * Resolve the OG image URL for a page using the chain:
     * 1. The page's own `og_image` media collection.
     * 2. The first image found in widget config (extractFirstImage).
     * 3. The site-wide `site_default_og_image` setting.
     * 4. Empty string.
     */
    public static function resolveOgImage(Page $page): string
    {
        $media = $page->getFirstMedia('og_image');
        if ($media) {
            return $media->getUrl();
        }

        $fromWidgets = static::extractFirstImage($page);
        if ($fromWidgets) {
            return $fromWidgets;
        }

        $siteDefault = SiteSetting::get('site_default_og_image', '');
        if ($siteDefault) {
            return Storage::disk('public')->url($siteDefault);
        }

        return '';
    }

    /**
     * Extract fallback meta description from the first ~160 characters of visible widget text.
     */
    public static function extractDescription(Page $page): string
    {
        $widgets = $page->pageWidgets()
            ->with('widgetType')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $text = '';

        foreach ($widgets as $pw) {
            $widgetType = $pw->widgetType;
            if (! $widgetType) {
                continue;
            }

            $config = $pw->config ?? [];

            foreach ($widgetType->config_schema ?? [] as $field) {
                $type = $field['type'] ?? '';
                $key  = $field['key'] ?? '';

                if (in_array($type, ['richtext', 'textarea', 'text']) && ! empty($config[$key])) {
                    $stripped = strip_tags($config[$key]);
                    $stripped = preg_replace('/\s+/', ' ', trim($stripped));

                    if ($stripped) {
                        $text .= ($text ? ' ' : '') . $stripped;
                    }

                    if (mb_strlen($text) >= 160) {
                        break 2;
                    }
                }
            }
        }

        if (! $text) {
            return '';
        }

        return mb_strlen($text) > 160 ? mb_substr($text, 0, 157) . '...' : $text;
    }

    /**
     * Find the first image URL from page widget config fields.
     */
    public static function extractFirstImage(Page $page): string
    {
        $widgets = $page->pageWidgets()
            ->with('widgetType')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($widgets as $pw) {
            $widgetType = $pw->widgetType;
            if (! $widgetType) {
                continue;
            }

            $config = $pw->config ?? [];

            foreach ($widgetType->config_schema ?? [] as $field) {
                $type = $field['type'] ?? '';
                $key  = $field['key'] ?? '';

                // Check image-type config fields via media library
                if ($type === 'image' && ! empty($config[$key])) {
                    $media = $pw->getFirstMedia("config_{$key}");
                    if ($media) {
                        return $media->getUrl();
                    }
                }

                // Check richtext fields for inline <img> tags
                if ($type === 'richtext' && ! empty($config[$key])) {
                    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $config[$key], $matches)) {
                        return $matches[1];
                    }
                }
            }
        }

        return '';
    }

    /**
     * Generate JSON-LD structured data array for a page.
     */
    private static function generateJsonLd(
        Page $page,
        string $title,
        string $description,
        string $ogImage,
        string $canonical,
        string $siteName,
    ): ?array {
        $base = [
            '@context' => 'https://schema.org',
        ];

        if ($page->type === 'post') {
            $page->loadMissing('author');

            $data = $base + [
                '@type'         => 'BlogPosting',
                'headline'      => $title,
                'description'   => $description,
                'url'           => $canonical,
                'datePublished' => $page->published_at?->toIso8601String(),
                'dateModified'  => $page->updated_at->toIso8601String(),
                'publisher'     => [
                    '@type' => 'Organization',
                    'name'  => $siteName,
                ],
            ];

            if ($page->author) {
                $data['author'] = [
                    '@type' => 'Person',
                    'name'  => $page->author->name,
                ];
            }

            if ($ogImage) {
                $data['image'] = $ogImage;
            }

            return $data;
        }

        if ($page->type === 'event') {
            $event = Event::where('landing_page_id', $page->id)->first();

            $data = $base + [
                '@type'       => 'Event',
                'name'        => $title,
                'description' => $description,
                'url'         => $canonical,
                'organizer'   => [
                    '@type' => 'Organization',
                    'name'  => $siteName,
                ],
            ];

            if ($event?->starts_at) {
                $data['startDate'] = $event->starts_at->toIso8601String();
            }
            if ($event?->ends_at) {
                $data['endDate'] = $event->ends_at->toIso8601String();
            }

            if ($ogImage) {
                $data['image'] = $ogImage;
            }

            return $data;
        }

        // Default: WebPage
        return $base + array_filter([
            '@type'        => 'WebPage',
            'name'         => $title,
            'description'  => $description,
            'url'          => $canonical,
            'dateModified' => $page->updated_at->toIso8601String(),
            'image'        => $ogImage ?: null,
        ]);
    }
}
