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

    private const CASE_MAP = [
        'uppercase'  => 'text-transform: uppercase',
        'lowercase'  => 'text-transform: lowercase',
        'capitalize' => 'text-transform: capitalize',
        'small-caps' => 'font-variant: small-caps',
        'none'       => 'text-transform: none',
    ];

    public static function compile(?array $typography = null): string
    {
        $resolved = TypographyResolver::resolve($typography);
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

        $resolved = TypographyResolver::resolve($typography);
        $blocks   = [];

        foreach (self::ELEMENT_SELECTORS as $key => $selector) {
            $config = $resolved['elements'][$key] ?? null;
            if (! $config) {
                continue;
            }

            $decls = self::elementDeclarations($key, $config);
            if (! $decls) {
                continue;
            }

            $prefixed = array_map(fn ($scope) => $scope . ' ' . $selector, $scopes);
            $blocks[] = implode(', ', $prefixed) . ' { ' . implode('; ', $decls) . '; }';
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

        $sizeVal  = $font['size']['value'] ?? null;
        $sizeUnit = $font['size']['unit'] ?? 'rem';
        if ($sizeVal !== null && $sizeVal !== '') {
            $decls[] = 'font-size: ' . $sizeVal . $sizeUnit;
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
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                $val = $config[$box][$side] ?? null;
                if ($val === null || $val === '') {
                    continue;
                }
                $decls[] = $box . '-' . $side . ': ' . (int) $val . 'px';
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
}
