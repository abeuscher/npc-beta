<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
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
        'a'    => ['href', 'title', 'class', 'target', 'rel'],
        'span' => ['class', 'data-heroicon', 'aria-hidden'],
        'img'  => ['src', 'alt', 'width', 'height', 'style', 'class'],
        'svg'  => ['xmlns', 'viewbox', 'fill', 'stroke', 'stroke-width', 'aria-hidden'],
        'path' => ['d', 'stroke-linecap', 'stroke-linejoin', 'fill', 'stroke', 'stroke-width'],
        'li'   => ['class', 'data-list'],
        // Table cells (session 349, Table widget). colspan/rowspan are
        // structural and pass through validated as bounded positive integers
        // (see SPAN_MAX); class carries the constrained alignment helper
        // (np-table-cell--{left,center,right}) and survives via the standard
        // arbitrary-class allowance, which is non-executable. style / script /
        // event-handlers are still stripped — this is untrusted (pasted) input.
        'table' => ['class'],
        'tr'    => ['class'],
        'td'    => ['class', 'colspan', 'rowspan'],
        'th'    => ['class', 'colspan', 'rowspan'],
    ];

    // Upper bound on a validated colspan/rowspan — large enough for any real
    // table, small enough to reject absurd values smuggled through paste.
    private const SPAN_MAX = 1000;

    // Session 305 (companion change §6.2 of inline-formatting-toolbar-spec):
    // the inline toolbar's link popover saves "Open in new tab" as
    // target="_blank" rel="noopener noreferrer". Allow exactly that here —
    // strict allow-lists for both, not just permit-anything — so an
    // attacker can't smuggle e.g. rel="nofollow noopener" or
    // target="javascript". Anything outside these sets is stripped on save.
    private const ALLOWED_TARGET_VALUES = ['_blank', '_self'];
    private const ALLOWED_REL_TOKENS    = ['noopener', 'noreferrer'];

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

            if (($name === 'colspan' || $name === 'rowspan') && ! self::isBoundedSpan($value)) {
                $element->removeAttribute($attr->nodeName);
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

            if ($name === 'target' && ! in_array(strtolower($value), self::ALLOWED_TARGET_VALUES, true)) {
                $element->removeAttribute('target');
                continue;
            }

            if ($name === 'rel') {
                $tokens   = preg_split('/\s+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $filtered = array_values(array_filter($tokens, fn ($t) => in_array($t, self::ALLOWED_REL_TOKENS, true)));
                if ($filtered === []) {
                    $element->removeAttribute('rel');
                } else {
                    $element->setAttribute('rel', implode(' ', array_unique($filtered)));
                }
                continue;
            }
        }
    }

    private static function isBoundedSpan(string $value): bool
    {
        if (preg_match('/^[0-9]+$/', trim($value)) !== 1) {
            return false;
        }

        $n = (int) $value;

        return $n >= 1 && $n <= self::SPAN_MAX;
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
