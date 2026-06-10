<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMText;

/**
 * Render-time semantic normalisation for stored rich-text (Quill) HTML.
 *
 * Non-destructive: this runs on the {!! !!}-emitted output on the render path
 * (App\Services\WidgetRenderer), never on the stored value — so already-published
 * content is corrected immediately without a backfill, and the editor's own
 * round-trip (which seeds from the raw config via the store, not from the
 * rendered node) is left untouched. If stored HTML ever needs permanent
 * cleaning, the same call drops into App\Support\HtmlSanitizer at save time.
 *
 * Two fixes, both for crawlable / semantically-correct public HTML:
 *
 *  1. Bullet lists. Quill v2 represents <ul><li> as <ol><li data-list="bullet">
 *     (see resources/js/page-builder-vue/composables/useInlineEdit.ts). A list
 *     whose direct items are ALL bullets is rewritten to a real <ul> and the now
 *     -redundant data-list="bullet" is dropped. Genuine ordered lists (a plain
 *     <ol>, or data-list="ordered") and mixed lists are left untouched.
 *
 *  2. Heading-wrapping bold. The editor emits <h2><strong>…</strong></h2>. A
 *     heading is already bold, so a <strong>/<b> wrapping the ENTIRE heading is
 *     unwrapped. Bold covering only part of a heading is left untouched.
 */
final class RichTextSemantics
{
    private const HEADINGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    public static function normalize(string $html): string
    {
        // Cheap guard: only the bullet-<ol> and heading transforms below can
        // fire, so skip the DOM round-trip for content with neither.
        if (stripos($html, '<ol') === false && ! preg_match('/<h[1-6][\s>]/i', $html)) {
            return $html;
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        $wrapped = '<?xml encoding="UTF-8"?><root>' . $html . '</root>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementsByTagName('root')->item(0);
        if (! $root instanceof DOMElement) {
            return $html;
        }

        self::bulletOlToUl($dom, $root);
        self::unwrapHeadingBold($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        // Restore {{tokens}} that saveHTML percent-encodes inside attributes
        // (mirrors HtmlSanitizer) — page-context substitution runs after this.
        return preg_replace('/%7B%7B([a-zA-Z0-9_.\-]+)%7D%7D/', '{{$1}}', $out) ?? $out;
    }

    /**
     * Rewrite each <ol> whose direct <li> children are all bullets into a <ul>,
     * dropping the redundant data-list="bullet". Ordered/mixed lists untouched.
     */
    private static function bulletOlToUl(DOMDocument $dom, DOMElement $root): void
    {
        foreach (iterator_to_array($root->getElementsByTagName('ol')) as $ol) {
            if (! $ol instanceof DOMElement) {
                continue;
            }

            $items = [];
            foreach ($ol->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->nodeName) === 'li') {
                    $items[] = $child;
                }
            }

            if ($items === []) {
                continue;
            }

            foreach ($items as $li) {
                if (strtolower($li->getAttribute('data-list')) !== 'bullet') {
                    continue 2; // not a pure bullet list — leave this <ol> alone
                }
            }

            $ul = $dom->createElement('ul');
            foreach (iterator_to_array($ol->attributes) as $attr) {
                $ul->setAttribute($attr->nodeName, $attr->nodeValue);
            }
            while ($ol->firstChild) {
                $ul->appendChild($ol->firstChild);
            }
            foreach ($items as $li) {
                $li->removeAttribute('data-list');
            }

            $ol->parentNode?->replaceChild($ul, $ol);
        }
    }

    /**
     * Unwrap a <strong>/<b> that wraps the entire content of a heading.
     */
    private static function unwrapHeadingBold(DOMElement $root): void
    {
        foreach (self::HEADINGS as $tag) {
            foreach (iterator_to_array($root->getElementsByTagName($tag)) as $heading) {
                if (! $heading instanceof DOMElement) {
                    continue;
                }

                $wrapper = self::soleBoldChild($heading);
                if ($wrapper === null) {
                    continue;
                }

                while ($wrapper->firstChild) {
                    $heading->insertBefore($wrapper->firstChild, $wrapper);
                }
                $heading->removeChild($wrapper);
            }
        }
    }

    /**
     * The single <strong>/<b> that is a heading's only meaningful child, or null.
     */
    private static function soleBoldChild(DOMElement $heading): ?DOMElement
    {
        $element = null;

        foreach ($heading->childNodes as $child) {
            if ($child instanceof DOMText) {
                if (trim($child->wholeText) !== '') {
                    return null; // meaningful text outside the wrapper
                }
                continue;
            }

            if (! $child instanceof DOMElement) {
                return null; // comment / other node — don't touch
            }

            if ($element !== null) {
                return null; // more than one element child
            }

            $element = $child;
        }

        if ($element === null) {
            return null;
        }

        return in_array(strtolower($element->nodeName), ['strong', 'b'], true) ? $element : null;
    }
}
