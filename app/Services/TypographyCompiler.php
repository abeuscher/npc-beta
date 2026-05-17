<?php

namespace App\Services;

class TypographyCompiler
{
    private const ELEMENT_SELECTORS = [
        'h1'    => 'h1:not(nav h1)',
        'h2'    => 'h2:not(nav h2)',
        'h3'    => 'h3:not(nav h3)',
        'h4'    => 'h4:not(nav h4)',
        'h5'    => 'h5:not(nav h5)',
        'h6'    => 'h6:not(nav h6)',
        'p'     => 'p:not(nav p)',
        'ul_li' => 'ul:not(nav ul) li',
        'ol_li' => 'ol:not(nav ol) li',
    ];

    /** Narrower-breakpoint @media max-widths (px), keyed by size-shape key. */
    private const BREAKPOINT_MAXWIDTH = [
        'lg' => 992,
        'md' => 768,
        'sm' => 576,
    ];

    private const CASE_MAP = [
        'uppercase'  => 'text-transform: uppercase',
        'lowercase'  => 'text-transform: lowercase',
        'capitalize' => 'text-transform: capitalize',
        'small-caps' => 'font-variant: small-caps',
        'none'       => 'text-transform: none',
    ];

    public static function compile(?array $typography = null): string
    {
        $resolved = TypographyResolver::migrate(TypographyResolver::resolve($typography));
        $blocks   = [];

        foreach (self::ELEMENT_SELECTORS as $key => $selector) {
            $config = $resolved['elements'][$key] ?? null;
            if (! $config) {
                continue;
            }

            $decls = self::elementDeclarations($key, $config);
            if ($decls) {
                $blocks[] = $selector . ' { ' . implode('; ', $decls) . '; }';
            }
            foreach (self::mediaSizeBlocks($config, [$selector]) as $mediaBlock) {
                $blocks[] = $mediaBlock;
            }
        }

        return implode("\n", $blocks);
    }

    /**
     * Compile typography with each selector prefixed by the given scopes, so the
     * rules apply only inside those containers (e.g. the page-builder preview and
     * the Quill rich-text editor). Multiple scopes produce a grouped selector.
     *
     * @param  array<int, string>  $scopes
     */
    public static function compileScoped(array $scopes, ?array $typography = null): string
    {
        $scopes = array_values(array_filter(array_map('trim', $scopes)));
        if (! $scopes) {
            return '';
        }

        $resolved = TypographyResolver::migrate(TypographyResolver::resolve($typography));
        $blocks   = [];

        foreach (self::ELEMENT_SELECTORS as $key => $selector) {
            $config = $resolved['elements'][$key] ?? null;
            if (! $config) {
                continue;
            }

            $prefixed = array_map(fn ($scope) => $scope . ' ' . $selector, $scopes);

            $decls = self::elementDeclarations($key, $config);
            if ($decls) {
                $blocks[] = implode(', ', $prefixed) . ' { ' . implode('; ', $decls) . '; }';
            }
            foreach (self::mediaSizeBlocks($config, $prefixed) as $mediaBlock) {
                $blocks[] = $mediaBlock;
            }
        }

        return implode("\n", $blocks);
    }

    /**
     * Return the set of Google Font family names referenced by the resolved typography.
     * Only families that appear in the curated catalog are returned.
     *
     * @return array<int, string>
     */
    public static function googleFontsUsed(?array $typography = null): array
    {
        $resolved = TypographyResolver::resolve($typography);
        $found    = [];
        $catalog  = TypographyResolver::googleFontNames();

        $families = [];
        foreach (['heading_family', 'body_family', 'nav_family'] as $bucket) {
            $families[] = $resolved['buckets'][$bucket] ?? null;
        }
        foreach ($resolved['elements'] as $el) {
            $families[] = $el['font']['family'] ?? null;
        }

        foreach ($families as $stack) {
            if (! $stack) {
                continue;
            }
            foreach ($catalog as $name) {
                if (str_contains($stack, $name) && ! in_array($name, $found, true)) {
                    $found[] = $name;
                }
            }
        }

        return $found;
    }

    private static function elementDeclarations(string $key, array $config): array
    {
        $decls = [];
        $font  = $config['font'] ?? [];

        if (! empty($font['family'])) {
            $decls[] = 'font-family: ' . $font['family'];
        }
        if (! empty($font['weight'])) {
            $decls[] = 'font-weight: ' . $font['weight'];
        }

        $xl = self::sizeAt($font, 'xl');
        if ($xl !== null) {
            $decls[] = 'font-size: ' . $xl['value'] . $xl['unit'];
        }

        if (isset($font['line_height']) && $font['line_height'] !== null && $font['line_height'] !== '') {
            $decls[] = 'line-height: ' . $font['line_height'];
        }

        $lsVal  = $font['letter_spacing']['value'] ?? null;
        $lsUnit = $font['letter_spacing']['unit'] ?? 'em';
        if ($lsVal !== null && $lsVal !== '') {
            $decls[] = 'letter-spacing: ' . $lsVal . $lsUnit;
        }

        $case = $font['case'] ?? null;
        if ($case && isset(self::CASE_MAP[$case])) {
            $decls[] = self::CASE_MAP[$case];
        }

        foreach (['margin', 'padding'] as $box) {
            // The box carries a single unit for all four sides (px if absent).
            // Honour it — values may be fractional (e.g. 1.5rem); never
            // int-truncate to px (the long-standing bug that rendered every
            // configured rem margin as `1px`).
            $unit = $config[$box]['unit'] ?? 'px';
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                $val = $config[$box][$side] ?? null;
                if ($val === null || $val === '' || ! is_numeric($val)) {
                    continue;
                }
                $decls[] = $box . '-' . $side . ': ' . ($val + 0) . $unit;
            }
        }

        if (in_array($key, ['ul_li', 'ol_li'], true)) {
            if (! empty($config['list_style_type'])) {
                $decls[] = 'list-style-type: ' . $config['list_style_type'];
            }
            if (! empty($config['marker_color'])) {
                $decls[] = '--np-list-marker-color: ' . $config['marker_color'];
            }
        }

        return $decls;
    }

    /**
     * The font-size at a given breakpoint key, or null if absent/blank.
     * Reads the per-breakpoint shape; falls back to a legacy flat
     * {value,unit} for xl (defensive — callers migrate() first).
     *
     * @return array{value: mixed, unit: string}|null
     */
    private static function sizeAt(array $font, string $bp): ?array
    {
        $size = $font['size'] ?? null;
        if (! is_array($size)) {
            return null;
        }

        if (isset($size[$bp]) && is_array($size[$bp])) {
            $v = $size[$bp]['value'] ?? null;

            return ($v === null || $v === '')
                ? null
                : ['value' => $v, 'unit' => $size[$bp]['unit'] ?? 'rem'];
        }

        if ($bp === 'xl' && array_key_exists('value', $size)) {
            $v = $size['value'];

            return ($v === null || $v === '')
                ? null
                : ['value' => $v, 'unit' => $size['unit'] ?? 'rem'];
        }

        return null;
    }

    /**
     * The three narrower-breakpoint @media blocks for one element: each
     * re-declares only font-size at that breakpoint's ramped value, wrapped
     * in @media (max-width: Npx). line-height stays the unitless base value
     * (rides the size) and heading margin-bottom stays em-relative — neither
     * needs per-breakpoint emission.
     *
     * @param  array<int, string>  $selectors
     * @return array<int, string>
     */
    private static function mediaSizeBlocks(array $config, array $selectors): array
    {
        $font = $config['font'] ?? [];
        $out  = [];

        foreach (self::BREAKPOINT_MAXWIDTH as $bp => $maxWidth) {
            $size = self::sizeAt($font, $bp);
            if ($size === null) {
                continue;
            }
            $out[] = '@media (max-width: ' . $maxWidth . 'px) { '
                . implode(', ', $selectors)
                . ' { font-size: ' . $size['value'] . $size['unit'] . '; } }';
        }

        return $out;
    }
}
