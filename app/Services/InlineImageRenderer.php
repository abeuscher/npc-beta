<?php

namespace App\Services;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

class InlineImageRenderer
{
    public static function process(string $html): string
    {
        if (! str_contains($html, '<img')) {
            return $html;
        }

        // Extract all src URLs from <img> tags
        preg_match_all('/<img[^>]+src="([^"]+)"[^>]*>/i', $html, $matches);
        $srcUrls = array_unique($matches[1] ?? []);

        if (empty($srcUrls)) {
            return $html;
        }

        // Extract filenames from URLs to match against media records.
        // Spatie URLs follow the pattern /storage/{id}/{file_name}
        $fileNames = [];
        foreach ($srcUrls as $url) {
            $basename = basename(parse_url($url, PHP_URL_PATH) ?: '');
            if ($basename !== '') {
                $fileNames[$basename] = true;
            }
        }

        if (empty($fileNames)) {
            return $html;
        }

        $mediaItems = Media::where('collection_name', 'inline-images')
            ->whereIn('file_name', array_keys($fileNames))
            ->get()
            ->keyBy('file_name');

        if ($mediaItems->isEmpty()) {
            return $html;
        }

        return preg_replace_callback(
            '/<img([^>]+)src="([^"]+)"([^>]*?)\s*\/?\s*>/i',
            function ($match) use ($mediaItems) {
                $basename = basename(parse_url($match[2], PHP_URL_PATH) ?: '');
                $media = $mediaItems->get($basename);

                if (! $media) {
                    return $match[0];
                }

                $attrs = $match[1] . $match[3];

                $alt = '';
                if (preg_match('/alt="([^"]*)"/', $attrs, $altMatch)) {
                    $alt = $altMatch[1];
                }

                $style = '';
                if (preg_match('/style="([^"]*)"/', $attrs, $styleMatch)) {
                    $style = $styleMatch[1];
                }

                return static::renderPicture($media, $alt, $style);
            },
            $html
        );
    }

    private static function renderPicture(Media $media, string $alt, string $style): string
    {
        $isSvg = str_contains($media->mime_type, 'svg');
        $hasConversions = count($media->generated_conversions ?? []) > 0;
        $originalUrl = $media->getUrl();

        $styleAttr = $style ? ' style="' . e($style) . '"' : '';

        if ($isSvg || ! $hasConversions) {
            return '<img src="' . e($originalUrl) . '" alt="' . e($alt) . '" loading="lazy"' . $styleAttr . '>';
        }

        // Build WebP srcset from responsive conversions
        $webpParts = [];
        foreach ($media->generated_conversions as $name => $generated) {
            if (! $generated) {
                continue;
            }
            if (str_starts_with($name, 'responsive-')) {
                $w = (int) str_replace('responsive-', '', $name);
                $webpParts[$w] = e($media->getUrl($name)) . " {$w}w";
            }
        }

        if (! empty($media->generated_conversions['webp'])) {
            $maxW = $media->getCustomProperty('width', 0);
            if ($maxW && ! isset($webpParts[$maxW])) {
                $webpParts[$maxW] = e($media->getUrl('webp')) . " {$maxW}w";
            }
        }

        if (empty($webpParts)) {
            return '<img src="' . e($originalUrl) . '" alt="' . e($alt) . '" loading="lazy"' . $styleAttr . '>';
        }

        krsort($webpParts);
        $webpSrcset = implode(', ', $webpParts);

        return '<picture>'
            . '<source type="image/webp" srcset="' . $webpSrcset . '" sizes="100vw">'
            . '<img src="' . e($originalUrl) . '" alt="' . e($alt) . '" loading="lazy"' . $styleAttr . '>'
            . '</picture>';
    }
}
