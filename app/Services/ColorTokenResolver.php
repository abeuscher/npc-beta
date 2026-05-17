<?php

namespace App\Services;

use App\Models\SiteSetting;

class ColorTokenResolver
{
    /**
     * Tier-1 user-tunable tokens: ordered list of token keys. The CSS custom
     * property is `--np-color-<key>`. Every token carries a concrete default
     * (concrete-values rule) pinned byte-exact to the pre-297 rendered values
     * so demotion of the `$color-*` SCSS palette is non-destructive at zero
     * overrides.
     */
    public const TIER1 = [
        'brand',
        'bg',
        'surface',
        'text',
        'heading',
        'text-muted',
        'link',
        'border',
        'header-bg',
        'footer-bg',
        'nav-link',
        'nav-hover',
        'nav-active',
    ];

    public const TIER1_LABELS = [
        'brand'      => 'Brand',
        'bg'         => 'Page Background',
        'surface'    => 'Surface',
        'text'       => 'Body Text',
        'heading'    => 'Headings',
        'text-muted' => 'Muted Text',
        'link'       => 'Links',
        'border'     => 'Borders',
        'header-bg'  => 'Header Background',
        'footer-bg'  => 'Footer Background',
        'nav-link'   => 'Nav Link',
        'nav-hover'  => 'Nav Link (Hover)',
        'nav-active' => 'Nav Link (Active)',
    ];

    private const TIER1_DEFAULTS = [
        'brand'      => '#0172ad',
        'bg'         => '#ffffff',
        'surface'    => '#f3f4f6',
        'text'       => '#1f2937',
        'heading'    => '#111827',
        'text-muted' => '#6b7280',
        'link'       => '#0172ad',
        'border'     => '#e5e7eb',
        'header-bg'  => '#ffffff',
        'footer-bg'  => '#ffffff',
        'nav-link'   => '#373c44',
        'nav-hover'  => '#0172ad',
        'nav-active' => '#0172ad',
    ];

    /**
     * Tier-2 published-but-not-user-tunable concrete constants. Surfaced to
     * widget devs (the contract surface is wider than the user knob set) but
     * never shown on the Theme page. Pinned byte-exact to the current
     * `$color-*` SCSS values so the demotion is non-destructive.
     */
    public const TIER2_CONSTANTS = [
        'success' => '#166534',
        'error'   => '#991b1b',
        'warning' => '#854d0e',
    ];

    private const HEX_PATTERN = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';

    /**
     * The tier-1 concrete defaults. Single source of truth — a fresh install
     * needs no seeded row; the relocation migration only writes a row when an
     * install's default template carried non-default colours.
     */
    public static function defaults(): array
    {
        return self::TIER1_DEFAULTS;
    }

    /**
     * Load the tier-1 palette from SiteSetting, merging onto defaults so every
     * token is always a concrete value. Unknown keys and non-hex values are
     * dropped back to their default (concrete-values rule).
     */
    public static function load(): array
    {
        $raw = SiteSetting::get('theme_colors');
        $raw = is_array($raw) ? $raw : [];

        $out = self::TIER1_DEFAULTS;
        foreach (self::TIER1 as $key) {
            $val = $raw[$key] ?? null;
            if (is_string($val) && preg_match(self::HEX_PATTERN, $val)) {
                $out[$key] = $val;
            }
        }

        return $out;
    }

    /**
     * Stable resolve seam (mirrors TypographyResolver::resolve). Pass-through
     * under the concrete-values model; kept for future consumers.
     */
    public static function resolve(?array $colors = null): array
    {
        return $colors ?? self::load();
    }

    /**
     * The full emitted token map: tier-1 (resolved) + tier-2 (constants +
     * derived). `focus-ring` is a CSS-level alias of brand; `brand-contrast`
     * is the readable-on-brand colour derived from the resolved brand via WCAG
     * contrast (deterministic, cheap).
     *
     * @return array<string, string>  token key → CSS value
     */
    public static function emitMap(?array $colors = null): array
    {
        $tier1 = self::resolve($colors);

        $map = $tier1;
        foreach (self::TIER2_CONSTANTS as $key => $val) {
            $map[$key] = $val;
        }
        $map['brand-contrast'] = self::readableOn($tier1['brand']);
        $map['focus-ring'] = 'var(--np-color-brand)';

        return $map;
    }

    /**
     * Black or white, whichever has the higher WCAG contrast ratio against
     * $hex. Deterministic; used to derive --np-color-brand-contrast.
     */
    public static function readableOn(string $hex): string
    {
        $white = self::contrastRatio($hex, '#ffffff');
        $black = self::contrastRatio($hex, '#111827');

        return $white >= $black ? '#ffffff' : '#111827';
    }

    private static function contrastRatio(string $a, string $b): float
    {
        $la = self::relativeLuminance($a);
        $lb = self::relativeLuminance($b);
        [$hi, $lo] = $la >= $lb ? [$la, $lb] : [$lb, $la];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    private static function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = self::rgb($hex);

        $lin = static function (float $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            (float) hexdec(substr($hex, 0, 2)),
            (float) hexdec(substr($hex, 2, 2)),
            (float) hexdec(substr($hex, 4, 2)),
        ];
    }
}
