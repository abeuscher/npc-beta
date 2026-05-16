<?php

namespace App\Services;

use App\Models\SiteSetting;

class TypographyResolver
{
    public const ELEMENTS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'ul_li', 'ol_li'];

    public const HEADING_ELEMENTS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    public const BODY_ELEMENTS = ['p', 'ul_li', 'ol_li'];

    public const SIZE_BREAKPOINTS = ['xl', 'lg', 'md', 'sm'];

    /**
     * Per-class scale ramp — the derived font-size at each narrower breakpoint
     * as a fraction of the xl (stored/desktop) value. Session-294 calibration
     * (gong/attio/clay); display phone biased to 0.60 (Attio-leaning, the
     * deliberately-restrained register) over the 0.54 raw 3-site mean. Section
     * is the midpoint of display and body (the calibration section row was the
     * noisy 2/3 data point). Body is 1.0 everywhere — no scaling.
     */
    private const RAMP = [
        'display' => ['lg' => 0.85, 'md' => 0.75, 'sm' => 0.60],
        'section' => ['lg' => 0.93, 'md' => 0.88, 'sm' => 0.80],
        'body'    => ['lg' => 1.0,  'md' => 1.0,  'sm' => 1.0],
    ];

    private const ELEMENT_CLASS = [
        'h1'    => 'display',
        'h2'    => 'section',
        'h3'    => 'section',
        'h4'    => 'body',
        'h5'    => 'body',
        'h6'    => 'body',
        'p'     => 'body',
        'ul_li' => 'body',
        'ol_li' => 'body',
    ];

    private const HEADING_MARGIN_BOTTOM_EM = [
        'h1' => 0.4,
        'h2' => 0.5,
        'h3' => 0.5,
        'h4' => 0.5,
        'h5' => 0.5,
        'h6' => 0.5,
    ];

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
                    'size'           => self::rampSize($el, ['value' => $d['size'], 'unit' => 'rem']),
                    'line_height'    => $d['line_height'],
                    'letter_spacing' => ['value' => 0, 'unit' => 'em'],
                    'case'           => 'none',
                ],
                'margin'  => $zeroSpacing,
                'padding' => $zeroSpacing,
            ];
            if (isset(self::HEADING_MARGIN_BOTTOM_EM[$el])) {
                $elements[$el]['heading_margin_bottom'] = self::HEADING_MARGIN_BOTTOM_EM[$el];
            }
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

        // Upgrade any legacy flat font.size ({value,unit}) to the per-breakpoint
        // {xl,lg,md,sm} shape *before* merging, so a flat stored value never
        // mergeDeep()s into a corrupt {xl,lg,md,sm,value,unit} hybrid. Operates
        // on the in-memory copy only — the stored SiteSetting row is never
        // rewritten on read (the row is rewritten only on an explicit save).
        return self::mergeDeep(self::defaults(), self::migrate($raw));
    }

    /**
     * Idempotent, non-destructive font.size shape upgrade. A flat
     * {value, unit} becomes { xl:{value,unit}, lg, md, sm } — the stored
     * value copied byte-exact into xl, lg/md/sm derived from the per-class
     * ramp. Already-per-breakpoint sizes (xl key present) are left untouched
     * so user-tuned lg/md/sm survive. Shared by load() (read) and the save
     * path (ThemeTypographyController::normalise) so both stay on one shape.
     */
    public static function migrate(array $typography): array
    {
        $elements = $typography['elements'] ?? null;
        if (! is_array($elements)) {
            return $typography;
        }

        foreach ($elements as $el => $config) {
            if (! is_array($config)) {
                continue;
            }
            $size = $config['font']['size'] ?? null;
            if (! is_array($size) || array_key_exists('xl', $size)) {
                continue; // unknown shape, or already per-breakpoint (idempotent)
            }
            if (! array_key_exists('value', $size)) {
                continue; // not the recognised flat shape — leave for the defaults merge
            }
            $typography['elements'][$el]['font']['size'] = self::rampSize(
                (string) $el,
                ['value' => $size['value'], 'unit' => $size['unit'] ?? 'rem'],
            );
        }

        return $typography;
    }

    /**
     * Expand an xl {value, unit} into the full { xl, lg, md, sm } set for an
     * element. xl is the byte-exact stored/desktop value; lg/md/sm are the
     * per-class ramp fractions of it (rounded to 4dp), same unit throughout.
     *
     * @param  array{value: mixed, unit?: string}  $xl
     */
    private static function rampSize(string $el, array $xl): array
    {
        $unit = $xl['unit'] ?? 'rem';
        $ramp = self::RAMP[self::ELEMENT_CLASS[$el] ?? 'body'];

        $size = ['xl' => ['value' => $xl['value'], 'unit' => $unit]];
        foreach (['lg', 'md', 'sm'] as $bp) {
            $size[$bp] = [
                'value' => round((float) $xl['value'] * $ramp[$bp], 4),
                'unit'  => $unit,
            ];
        }

        return $size;
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
