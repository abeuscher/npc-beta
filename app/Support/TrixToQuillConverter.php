<?php

namespace App\Support;

use DOMDocument;
use DOMElement;

final class TrixToQuillConverter
{
    public static function convert(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        if (! str_contains($html, '<div') && ! str_contains($html, '<figure')) {
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
            return '';
        }

        foreach (iterator_to_array($dom->getElementsByTagName('figure')) as $fig) {
            $fig->parentNode?->removeChild($fig);
        }

        self::convertDivs($root, $dom);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    private static function convertDivs(DOMElement $element, DOMDocument $dom): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                self::convertDivs($child, $dom);
            }
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            if (strtolower($child->nodeName) !== 'div') {
                continue;
            }

            if (strtolower($element->nodeName) === 'root') {
                $newP = $dom->createElement('p');
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $newP->setAttribute($attr->nodeName, $attr->nodeValue);
                }
                while ($child->firstChild) {
                    $newP->appendChild($child->firstChild);
                }
                $element->insertBefore($newP, $child);
                $element->removeChild($child);
            } else {
                while ($child->firstChild) {
                    $element->insertBefore($child->firstChild, $child);
                }
                $element->removeChild($child);
            }
        }
    }
}
