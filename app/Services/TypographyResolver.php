<?php

namespace App\Services;

use App\Models\SiteSetting;

class TypographyResolver
{
    public const ELEMENTS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'ul_li', 'ol_li'];

    public const HEADING_ELEMENTS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    public const BODY_ELEMENTS = ['p', 'ul_li', 'ol_li'];

    public const DEFAULT_SAMPLE_TEXT = 'The quick brown fox jumps over the lazy dog.';

    public const DEFAULT_FAMILY = "'Inter', system-ui, sans-serif";

    private const ELEMENT_DEFAULTS = [
        'h1'    => ['weight' => '700', 'size' => 2.5,   'line_height' => 1.2],
        'h2'    => ['weight' => '700', 'size' => 2.0,   'line_height' => 1.25],
        'h3'    => ['weight' => '700', 'size' => 1.5,   'line_height' => 1.3],
        'h4'    => ['weight' => '700', 'size' => 1.25,  'line_height' => 1.35],
        'h5'    => ['weight' => '600', 'size' => 1.125, 'line_height' => 1.4],
        'h6'    => ['weight' => '600', 'size' => 1.0,   'line_height' => 1.4],
        'p'     => ['weight' => '400', 'size' => 1.0,   'line_height' => 1.5],
        'ul_li' => ['weight' => '400', 'size' => 1.0,   'line_height' => 1.5],
        'ol_li' => ['weight' => '400', 'size' => 1.0,   'line_height' => 1.5],
    ];

    public static function fontCatalog(): array
    {
        return [
            ['value' => 'Georgia, serif',                           'label' => 'Georgia'],
            ['value' => "'Inter', system-ui, sans-serif",           'label' => 'Inter'],
            ['value' => "'Lato', system-ui, sans-serif",            'label' => 'Lato'],
            ['value' => "'Merriweather', Georgia, serif",           'label' => 'Merriweather'],
            ['value' => "'Montserrat', system-ui, sans-serif",      'label' => 'Montserrat'],
            ['value' => "'Open Sans', system-ui, sans-serif",       'label' => 'Open Sans'],
            ['value' => "'Playfair Display', Georgia, serif",       'label' => 'Playfair Display'],
            ['value' => "'Raleway', system-ui, sans-serif",         'label' => 'Raleway'],
            ['value' => "'Source Sans 3', system-ui, sans-serif",   'label' => 'Source Sans 3'],
        ];
    }

    public static function googleFontNames(): array
    {
        return ['Inter', 'Lato', 'Merriweather', 'Montserrat', 'Open Sans', 'Playfair Display', 'Raleway', 'Source Sans 3'];
    }

    public static function defaults(): array
    {
        $zeroSpacing = ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0];

        $elements = [];
        foreach (self::ELEMENTS as $el) {
            $d = self::ELEMENT_DEFAULTS[$el];
            $elements[$el] = [
                'font' => [
                    'family'         => self::DEFAULT_FAMILY,
                    'weight'         => $d['weight'],
                    'size'           => ['value' => $d['size'], 'unit' => 'rem'],
                    'line_height'    => $d['line_height'],
                    'letter_spacing' => ['value' => 0, 'unit' => 'em'],
                    'case'           => 'none',
                ],
                'margin'  => $zeroSpacing,
                'padding' => $zeroSpacing,
            ];
        }
        $elements['ul_li']['list_style_type'] = 'disc';
        $elements['ul_li']['marker_color']    = null;
        $elements['ol_li']['list_style_type'] = 'decimal';
        $elements['ol_li']['marker_color']    = null;

        return [
            'buckets' => [
                'heading_family' => null,
                'body_family'    => null,
                'nav_family'     => null,
            ],
            'elements'    => $elements,
            'sample_text' => self::DEFAULT_SAMPLE_TEXT,
        ];
    }

    /**
     * Load typography from SiteSetting, merging into defaults so new keys added later
     * in development don't crash downstream consumers.
     */
    public static function load(): array
    {
        $raw = SiteSetting::get('typography');
        $raw = is_array($raw) ? $raw : [];

        return self::mergeDeep(self::defaults(), $raw);
    }

    /**
     * Return the merged/loaded typography tree.
     *
     * Under the concrete-values model, every element carries its own non-null family/weight/size/etc,
     * so resolve() is effectively a pass-through. Kept as a stable seam for future consumers.
     */
    public static function resolve(?array $typography = null): array
    {
        return $typography ?? self::load();
    }

    private static function mergeDeep(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && ! array_is_list($base[$key])) {
                $base[$key] = self::mergeDeep($base[$key], $value);
            } elseif ($value !== null) {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
