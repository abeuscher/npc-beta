<?php

namespace App\Services;

class MapEmbedParser
{
    /**
     * Extract a Google Maps embed URL from user input.
     *
     * Accepts:
     * - An <iframe> snippet containing a Google Maps embed src
     * - A Google Maps place/@ URL
     * - A Google Maps short link (goo.gl/maps, maps.app.goo.gl)
     * - An existing /maps/embed URL
     *
     * Returns null if the input cannot be parsed as a Google Maps reference
     * or if the resulting URL host is not *.google.com.
     */
    public static function extractEmbedUrl(string $input): ?string
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        // 1. If input contains an <iframe>, extract the src attribute
        if (stripos($input, '<iframe') !== false) {
            return self::extractFromIframe($input);
        }

        // 2. If input is already an embed URL, return it directly
        if (preg_match('#^https?://[^/]*google\.com/maps/embed#i', $input)) {
            return self::sanitiseUrl($input);
        }

        // 3. Google Maps place URL: /maps/place/...
        //    Uses the keyless embed format (?q=...&output=embed)
        if (preg_match('#https?://[^/]*google\.com/maps/place/([^/?]+)#i', $input, $m)) {
            $query = urldecode($m[1]);
            return self::sanitiseUrl('https://www.google.com/maps?q=' . urlencode($query) . '&output=embed');
        }

        // 4. Google Maps @ URL: /maps/@lat,lng,...
        if (preg_match('#https?://[^/]*google\.com/maps/@([0-9.\-]+),([0-9.\-]+)#i', $input, $m)) {
            $query = $m[1] . ',' . $m[2];
            return self::sanitiseUrl('https://www.google.com/maps?q=' . urlencode($query) . '&output=embed');
        }

        // 5. Short links (goo.gl/maps/, maps.app.goo.gl/)
        if (preg_match('#https?://(goo\.gl/maps/|maps\.app\.goo\.gl/)#i', $input)) {
            return self::sanitiseUrl('https://www.google.com/maps?q=' . urlencode($input) . '&output=embed');
        }

        // 6. Generic google.com/maps URL (catch-all for other formats)
        if (preg_match('#https?://[^/]*google\.com/maps#i', $input)) {
            return self::sanitiseUrl('https://www.google.com/maps?q=' . urlencode($input) . '&output=embed');
        }

        return null;
    }

    private static function extractFromIframe(string $html): ?string
    {
        if (preg_match('/src=["\']([^"\']+)["\']/i', $html, $m)) {
            $src = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            return self::sanitiseUrl($src);
        }

        return null;
    }

    private static function sanitiseUrl(string $url): ?string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        // Only allow *.google.com hosts
        if ($host === 'google.com' || str_ends_with($host, '.google.com')) {
            return $url;
        }

        return null;
    }
}
