<?php

namespace App\Services\Media;

class SvgSanitizer
{
    /**
     * Sanitize SVG content by removing executable elements and attributes.
     * Returns the cleaned SVG string, or null if the input is not valid XML.
     */
    public static function sanitize(string $svg): ?string
    {
        if (!static::isValidXml($svg)) {
            return null;
        }

        $dom = new \DOMDocument();
        $dom->formatOutput = false;

        // Suppress warnings from malformed SVG, but catch actual parse failures
        $internalErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOENT);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        if (!$loaded) {
            return null;
        }

        static::removeElements($dom, 'script');
        static::removeElements($dom, 'foreignObject');
        static::removeExternalUseElements($dom);
        static::removeEventAttributes($dom);
        static::removeJavascriptUris($dom);

        // Extract just the SVG content (skip XML declaration)
        $svgElement = $dom->getElementsByTagName('svg')->item(0);
        if (!$svgElement) {
            return null;
        }

        return $dom->saveXML($svgElement);
    }

    /**
     * Check if the given string is valid XML.
     */
    public static function isValidXml(string $content): bool
    {
        $content = trim($content);
        if (empty($content)) {
            return false;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $result = $dom->loadXML($content, LIBXML_NONET | LIBXML_NOENT);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $result !== false;
    }

    /**
     * Check if content appears to be an SVG (basic detection).
     */
    public static function isSvg(string $content): bool
    {
        $trimmed = trim($content);
        return str_contains($trimmed, '<svg') && static::isValidXml($trimmed);
    }

    private static function removeElements(\DOMDocument $dom, string $tagName): void
    {
        $elements = $dom->getElementsByTagName($tagName);
        $toRemove = [];
        for ($i = 0; $i < $elements->length; $i++) {
            $toRemove[] = $elements->item($i);
        }
        foreach ($toRemove as $el) {
            $el->parentNode->removeChild($el);
        }
    }

    private static function removeExternalUseElements(\DOMDocument $dom): void
    {
        $uses = $dom->getElementsByTagName('use');
        $toRemove = [];

        for ($i = 0; $i < $uses->length; $i++) {
            $use = $uses->item($i);
            $href = $use->getAttribute('href') ?: $use->getAttributeNS('http://www.w3.org/1999/xlink', 'href');

            if ($href === '') {
                continue;
            }

            // Remove if href points to external URL or dangerous URI scheme
            if (preg_match('#^(https?://|//|javascript:|data:)#i', $href)) {
                $toRemove[] = $use;
            }
        }

        foreach ($toRemove as $el) {
            $el->parentNode->removeChild($el);
        }
    }

    private static function removeEventAttributes(\DOMDocument $dom): void
    {
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*');

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $toRemove = [];
            foreach ($node->attributes as $attr) {
                if (preg_match('/^on/i', $attr->name)) {
                    $toRemove[] = $attr->name;
                }
            }

            foreach ($toRemove as $attrName) {
                $node->removeAttribute($attrName);
            }
        }
    }

    private static function removeJavascriptUris(\DOMDocument $dom): void
    {
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*');

        $uriAttributes = ['href', 'xlink:href', 'src', 'action', 'formaction'];

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            foreach ($uriAttributes as $attr) {
                $value = $node->getAttribute($attr);
                if ($value && preg_match('/^\s*(javascript|data):/i', $value)) {
                    $node->removeAttribute($attr);
                }
            }
        }
    }
}
