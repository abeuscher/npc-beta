<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'u', 's',
        'ol', 'ul', 'li',
        'blockquote', 'pre', 'code',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'a', 'span', 'img',
        'div', 'section', 'article', 'header', 'footer', 'nav', 'aside', 'main',
        'figure', 'figcaption',
        'hr',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
        'dl', 'dt', 'dd',
        'mark', 'small', 'sub', 'sup', 'time', 'cite', 'abbr', 'q',
        'details', 'summary',
        'svg', 'path',
    ];

    private const VOID_DROP_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'noscript', 'noembed',
    ];

    private const ATTRS_PER_TAG = [
        'a'    => ['href', 'title', 'class'],
        'span' => ['class', 'data-heroicon', 'aria-hidden'],
        'img'  => ['src', 'alt', 'width', 'height', 'style', 'class'],
        'svg'  => ['xmlns', 'viewbox', 'fill', 'stroke', 'stroke-width', 'aria-hidden'],
        'path' => ['d', 'stroke-linecap', 'stroke-linejoin', 'fill', 'stroke', 'stroke-width'],
        'li'   => ['class', 'data-list'],
    ];

    public static function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        $wrapped = '<?xml encoding="UTF-8"?><root>' . $html . '</root>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementsByTagName('root')->item(0);
        if (! $root instanceof DOMElement) {
            return '';
        }

        self::walk($root, false);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return preg_replace('/%7B%7B([a-zA-Z0-9_.\-]+)%7D%7D/', '{{$1}}', $out) ?? $out;
    }

    private static function walk(DOMElement $element, bool $inHeroicon): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                $element->removeChild($child);
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::VOID_DROP_TAGS, true)) {
                $element->removeChild($child);
                continue;
            }

            $isHeroiconWrapper = $tag === 'span' && self::hasClassToken($child, 'ql-heroicon');
            $childInHeroicon = $inHeroicon || $isHeroiconWrapper;

            if (! self::isAllowed($tag, $childInHeroicon)) {
                self::unwrap($child, $childInHeroicon);
                continue;
            }

            self::filterAttributes($child, $tag);
            self::walk($child, $childInHeroicon);
        }
    }

    private static function isAllowed(string $tag, bool $inHeroicon): bool
    {
        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            return false;
        }

        if (($tag === 'svg' || $tag === 'path') && ! $inHeroicon) {
            return false;
        }

        return true;
    }

    private static function unwrap(DOMElement $element, bool $inHeroicon): void
    {
        self::walk($element, $inHeroicon);

        $parent = $element->parentNode;
        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private static function filterAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ATTRS_PER_TAG[$tag] ?? ['class'];

        foreach (iterator_to_array($element->attributes) as $attr) {
            $name  = strtolower($attr->nodeName);
            $value = $attr->nodeValue;

            if (str_starts_with($name, 'on')) {
                $element->removeAttribute($attr->nodeName);
                continue;
            }

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attr->nodeName);
                continue;
            }

            if ($name === 'class') {
                $filtered = self::filterClassTokens($value);
                if ($filtered === '') {
                    $element->removeAttribute('class');
                } else {
                    $element->setAttribute('class', $filtered);
                }
                continue;
            }

            if ($name === 'href' && ! self::isAllowedHrefUri($value)) {
                $element->removeAttribute('href');
                continue;
            }

            if ($name === 'src' && ! self::isAllowedSrcUri($value)) {
                $element->removeAttribute('src');
                continue;
            }
        }
    }

    private static function hasClassToken(DOMElement $element, string $token): bool
    {
        $class = $element->getAttribute('class');
        if ($class === '') {
            return false;
        }

        $tokens = preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return in_array($token, $tokens, true);
    }

    private static function filterClassTokens(string $class): string
    {
        $tokens = preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode(' ', $tokens);
    }

    private static function isAllowedHrefUri(string $url): bool
    {
        $url = trim($url);
        $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url) ?? '';

        if ($url === '') {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $m) === 1) {
            return in_array(strtolower($m[1]), ['http', 'https', 'mailto'], true);
        }

        return true;
    }

    private static function isAllowedSrcUri(string $url): bool
    {
        $url = trim($url);
        $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url) ?? '';

        if ($url === '') {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $m) === 1) {
            return in_array(strtolower($m[1]), ['http', 'https'], true);
        }

        return true;
    }
}
