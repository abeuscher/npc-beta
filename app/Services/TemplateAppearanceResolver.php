<?php

namespace App\Services;

use App\Models\Template;

/**
 * The single shared resolver: a Template record → the request-time inline
 * --np-color-* override string for its content-region wrapper, plus its
 * chrome slot resolution. Called by BOTH the public layout and the
 * page-builder preview with no divergent branch — that one-resolver
 * discipline is the only thing that guarantees preview fidelity (the reason
 * `.np-site` exists).
 *
 * Per-template scheme overrides are delivered request-time inline on a
 * content wrapper — never compiled into the public bundle (the session-295
 * stale-bundle lesson; deliberately outside the 296 drift guard, see
 * AssetBuildService::bundleDrift). Theme defaults stay bundle-compiled and
 * `.np-site`-scoped; this resolver only emits the per-template *delta*.
 *
 * Concrete-values rule: a fresh, unconfigured install resolves to the
 * Default scheme with no stored row — and the Default scheme is an empty
 * delta, so rendering is byte-identical to the 297 token defaults. The
 * scheme set selects among the fixed `--np-color-*` tokens
 * (docs/theme-color-tokens.md, 297) — it never adds or renames a token.
 */
class TemplateAppearanceResolver
{
    public const DEFAULT_SCHEME = 'default';

    /**
     * Content-region token deltas per scheme. Keys are the bare token name
     * (the CSS custom property is `--np-color-<key>`). A scheme only ever
     * reassigns the *content-region* tokens — never `brand`, never the chrome
     * tokens (`header-bg`, `footer-bg`, `nav-*`), never the Tier-2 constants.
     * Schemes recolour the content region only; chrome composes, never
     * bleeds. `default` is the empty delta — the bundle's `.np-site` 297
     * defaults stand unchanged (identity; byte-identical to today).
     *
     * `inverse` is a contrast-vetted dark content assignment over the fixed
     * 297 tokens (WCAG-legible against itself). The values are surfaced for
     * human confirmation with multi-surface captures; they are not a
     * mechanical bg↔text swap.
     */
    private const SCHEME_TOKENS = [
        'default' => [],
        'inverse' => [
            'bg'         => '#111827',
            'surface'    => '#1f2937',
            'text'       => '#f3f4f6',
            'heading'    => '#ffffff',
            'text-muted' => '#9ca3af',
            'link'       => '#5eb3e4',
            'border'     => '#374151',
        ],
    ];

    public const SCHEME_LABELS = [
        'default' => 'Default (light)',
        'inverse' => 'Inverse (dark)',
    ];

    /**
     * Ordered list of selectable scheme keys. A power-user named-scheme
     * config layer is an additive follow-up — the page/template author only
     * ever *selects* one, so the selector contract is stable either way.
     *
     * @return array<int, string>
     */
    public static function schemes(): array
    {
        return array_keys(self::SCHEME_TOKENS);
    }

    /**
     * The resolved, validated scheme key for a template. Null template, null
     * column (pre-migration / unconfigured), or an unknown value all fall
     * back to the Default scheme (concrete-values rule).
     */
    public static function schemeFor(?Template $template): string
    {
        $raw = $template?->getAttribute('scheme');

        return is_string($raw) && array_key_exists($raw, self::SCHEME_TOKENS)
            ? $raw
            : self::DEFAULT_SCHEME;
    }

    /**
     * The content-wrapper inline custom-property string for a template's
     * selected scheme — e.g. `--np-color-bg:#111827;--np-color-text:#f3f4f6`.
     * Empty string for the Default scheme: the wrapper carries no override
     * and the bundle's `.np-site` 297 defaults apply unchanged. Applied to
     * the content region only so the standard chrome keeps its vetted
     * colours (compose-not-bleed); never bundled.
     */
    public static function inlineVars(?Template $template): string
    {
        $tokens = self::SCHEME_TOKENS[self::schemeFor($template)] ?? [];

        $decls = [];
        foreach ($tokens as $key => $value) {
            $decls[] = '--np-color-' . $key . ':' . $value;
        }

        return implode(';', $decls);
    }
}
