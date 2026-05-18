<?php

// The single shared resolver: Template → request-time inline --np-color-*
// content-region override. Default scheme is the empty delta (concrete-values
// rule: a fresh, unconfigured install with no stored scheme renders
// byte-identical to the 297 token defaults). Schemes select among the FIXED
// 297 tokens (docs/theme-color-tokens.md) — they never add or rename a token,
// and they recolour the content region only (compose-not-bleed: never brand,
// never the chrome tokens).

use App\Models\Template;
use App\Services\ColorTokenResolver;
use App\Services\TemplateAppearanceResolver;
use Tests\TestCase;

uses(TestCase::class)->group('design');

// Tokens a scheme is allowed to reassign — the content region only.
const CONTENT_REGION_TOKENS = ['bg', 'surface', 'text', 'heading', 'text-muted', 'link', 'border'];

// Tokens a scheme must NEVER touch — brand identity + the chrome tokens
// (compose-not-bleed) + the Tier-2 published constants.
const SCHEME_FORBIDDEN_TOKENS = ['brand', 'header-bg', 'footer-bg', 'nav-link', 'nav-hover', 'nav-active'];

function schemeVarKeys(string $inline): array
{
    if ($inline === '') {
        return [];
    }

    return collect(explode(';', $inline))
        ->map(fn ($d) => trim(explode(':', $d, 2)[0]))
        ->filter()
        ->map(fn ($prop) => str_replace('--np-color-', '', $prop))
        ->values()
        ->all();
}

it('resolves the Default scheme to an empty delta with no stored row (concrete-values, byte-identical)', function () {
    expect(TemplateAppearanceResolver::inlineVars(null))->toBe('');

    $t = new Template(['name' => 'X', 'type' => 'page']);
    expect(TemplateAppearanceResolver::schemeFor($t))->toBe('default');
    expect(TemplateAppearanceResolver::inlineVars($t))->toBe('');
});

it('falls back to Default for an unknown/garbage stored scheme value', function () {
    $t = new Template(['name' => 'X', 'type' => 'page']);
    $t->setAttribute('scheme', 'neon-disco');
    expect(TemplateAppearanceResolver::schemeFor($t))->toBe('default');
    expect(TemplateAppearanceResolver::inlineVars($t))->toBe('');

    $t->setAttribute('scheme', 123);
    expect(TemplateAppearanceResolver::schemeFor($t))->toBe('default');
});

it('exposes a fixed selectable scheme set (Default + Inverse), the page author only selects', function () {
    expect(TemplateAppearanceResolver::schemes())->toBe(['default', 'inverse']);
});

it('emits the Inverse scheme as concrete content-region overrides', function () {
    $t = new Template(['name' => 'Landing', 'type' => 'page']);
    $t->setAttribute('scheme', 'inverse');

    $inline = TemplateAppearanceResolver::inlineVars($t);

    expect($inline)->not->toBe('');
    expect($inline)->toContain('--np-color-bg:#111827');
    expect($inline)->toContain('--np-color-text:#f3f4f6');
});

it('schemes recolour the content region only — never brand or chrome (compose-not-bleed)', function () {
    foreach (TemplateAppearanceResolver::schemes() as $scheme) {
        $t = new Template(['name' => 'T', 'type' => 'page']);
        $t->setAttribute('scheme', $scheme);

        $keys = schemeVarKeys(TemplateAppearanceResolver::inlineVars($t));

        foreach ($keys as $key) {
            expect(CONTENT_REGION_TOKENS)->toContain($key);
            expect(SCHEME_FORBIDDEN_TOKENS)->not->toContain($key);
        }
    }
});

it('only reassigns tokens that exist in the fixed 297 contract (no new tokens)', function () {
    $t = new Template(['name' => 'T', 'type' => 'page']);
    $t->setAttribute('scheme', 'inverse');

    foreach (schemeVarKeys(TemplateAppearanceResolver::inlineVars($t)) as $key) {
        expect(ColorTokenResolver::TIER1)->toContain($key);
    }
});

it('is one function, two call sites — the same Template yields the identical string (public↔preview parity)', function () {
    $t = new Template(['name' => 'Landing', 'type' => 'page']);
    $t->setAttribute('scheme', 'inverse');

    // Public layout and the page-builder preview both call this exact method
    // with the resolved Template; parity is structural, not a re-derivation.
    $publicCall  = TemplateAppearanceResolver::inlineVars($t);
    $previewCall = TemplateAppearanceResolver::inlineVars($t);

    expect($previewCall)->toBe($publicCall)->not->toBe('');
});
