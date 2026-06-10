<?php

namespace App\Services;

/**
 * Rewrites width-keyed @media rules into named @container rules for the
 * page-builder preview.
 *
 * The preview canvas simulates a viewport by setting a fixed pixel width on
 * the scope element and zooming it to fit the pane — so @media rules in the
 * public bundle evaluate against the real browser window, not the simulated
 * viewport, and the tablet/mobile presets render desktop values (typography
 * ramps, section-spacing compression, .site-container max-widths). The scope
 * element is an inline-size query container named np-viewport whose layout
 * width IS the preset, so rewriting `@media (max-width: 768px)` to
 * `@container np-viewport (max-width: 768px)` makes the same rules evaluate
 * faithfully inside the preview. The name is load-bearing: widgets declare
 * anonymous query containers of their own (e.g. event_mini_calendar), and an
 * unnamed @container would resolve against the nearest one of those instead
 * of the viewport scope.
 *
 * Only preludes consisting purely of width conditions (optionally prefixed
 * with screen/all media types) are rewritten; anything else — print,
 * prefers-reduced-motion, orientation, or width mixed with a non-width
 * feature — passes through untouched, since those have no container-query
 * equivalent. One known limit, by construction: a transformed rule whose
 * selector targets the scope element itself (.np-site root rules) cannot
 * match, because a container's own queries only apply to its descendants.
 *
 * The public bundle is never transformed — this variant is built alongside
 * it and loaded only by the admin panel.
 */
class EditorCssTransformer
{
    public const CONTAINER_NAME = 'np-viewport';

    private const WIDTH_ONLY_PRELUDE =
        '/^(?:(?:only\s+)?(?:screen|all)\s+and\s+)?'
        . '\(\s*(?:min|max)-width\s*:\s*[0-9.]+(?:px|em|rem)\s*\)'
        . '(?:\s+and\s+\(\s*(?:min|max)-width\s*:\s*[0-9.]+(?:px|em|rem)\s*\))*$/i';

    public static function transform(string $css): string
    {
        return preg_replace_callback(
            '/@media\s*([^{]+?)\s*\{/i',
            function (array $m): string {
                $prelude = trim($m[1]);
                if (! preg_match(self::WIDTH_ONLY_PRELUDE, $prelude)) {
                    return $m[0];
                }
                $conditions = preg_replace('/^(?:only\s+)?(?:screen|all)\s+and\s+/i', '', $prelude);

                return '@container ' . self::CONTAINER_NAME . ' ' . $conditions . ' {';
            },
            $css,
        );
    }
}
