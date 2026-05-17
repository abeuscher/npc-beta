<?php

// Data-preservation guard for the session-297 relocation (G1/285-shape
// discipline carried from 295): the default template's stored colours must
// land in the Theme byte-identical — a lost or mutated configured colour is
// silent data loss. Written before the migration per the relocation rule.

use App\Models\SiteSetting;
use App\Services\ColorTokenResolver;
use App\Services\ThemeColorRelocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// The exact values TemplateSeeder seeded onto the pre-297 Default template.
const SEEDED_DEFAULT_TEMPLATE_COLORS = [
    'primary_color'    => '#0172ad',
    'header_bg_color'  => '#ffffff',
    'footer_bg_color'  => '#ffffff',
    'nav_link_color'   => '#373c44',
    'nav_hover_color'  => '#0172ad',
    'nav_active_color' => '#0172ad',
];

it('maps the seeded default template colours to tokens byte-identically', function () {
    $map = ThemeColorRelocation::mapTemplateColors(SEEDED_DEFAULT_TEMPLATE_COLORS);

    expect($map['brand'])->toBe('#0172ad');
    expect($map['header-bg'])->toBe('#ffffff');
    expect($map['footer-bg'])->toBe('#ffffff');
    expect($map['nav-link'])->toBe('#373c44');
    expect($map['nav-hover'])->toBe('#0172ad');
    expect($map['nav-active'])->toBe('#0172ad');

    // Every mapped value is the exact stored string — no normalisation.
    foreach (ThemeColorRelocation::COLUMN_TO_TOKEN as $column => $token) {
        expect($map[$token])->toBe(SEEDED_DEFAULT_TEMPLATE_COLORS[$column]);
    }
});

it('passes arbitrary configured colours through with zero mutation', function () {
    $weird = [
        'primary_color'    => '#AbCdEf',   // mixed case preserved
        'header_bg_color'  => '#FFF',      // 3-digit preserved (not expanded)
        'footer_bg_color'  => '#000000',
        'nav_link_color'   => '#123abc',
        'nav_hover_color'  => '#DEADBE',
        'nav_active_color' => '#abc',
    ];

    $map = ThemeColorRelocation::mapTemplateColors($weird);

    expect($map['brand'])->toBe('#AbCdEf');
    expect($map['header-bg'])->toBe('#FFF');
    expect($map['footer-bg'])->toBe('#000000');
    expect($map['nav-link'])->toBe('#123abc');
    expect($map['nav-hover'])->toBe('#DEADBE');
    expect($map['nav-active'])->toBe('#abc');
});

it('keeps the seven no-column tokens at their concrete defaults', function () {
    $map = ThemeColorRelocation::mapTemplateColors(SEEDED_DEFAULT_TEMPLATE_COLORS);
    $defaults = ColorTokenResolver::defaults();

    foreach (['bg', 'surface', 'text', 'heading', 'text-muted', 'link', 'border'] as $token) {
        expect($map[$token])->toBe($defaults[$token]);
    }
    // Concrete-values rule: every tier-1 token present.
    expect(array_keys($map))->toEqualCanonicalizing(ColorTokenResolver::TIER1);
});

it('a null / empty column never overwrites its token default', function () {
    $map = ThemeColorRelocation::mapTemplateColors([
        'primary_color'   => null,
        'header_bg_color' => '',
        // others absent
    ]);
    $defaults = ColorTokenResolver::defaults();

    expect($map['brand'])->toBe($defaults['brand']);
    expect($map['header-bg'])->toBe($defaults['header-bg']);
    expect($map['nav-link'])->toBe($defaults['nav-link']);
});

it('round-trips byte-identically through the theme_colors SiteSetting', function () {
    $map = ThemeColorRelocation::mapTemplateColors(SEEDED_DEFAULT_TEMPLATE_COLORS);

    SiteSetting::updateOrCreate(
        ['key' => 'theme_colors'],
        ['value' => json_encode($map), 'type' => 'json', 'group' => 'design'],
    );

    $loaded = ColorTokenResolver::load();

    foreach ($map as $token => $value) {
        expect($loaded[$token])->toBe($value);
    }
});

it('returns concrete defaults byte-identically when no theme_colors row exists', function () {
    // Fresh-install path: TemplateSeeder no longer seeds colours; the resolver
    // defaults must equal the pre-297 seeded values exactly.
    expect(SiteSetting::where('key', 'theme_colors')->exists())->toBeFalse();

    $loaded = ColorTokenResolver::load();

    expect($loaded['brand'])->toBe('#0172ad');
    expect($loaded['header-bg'])->toBe('#ffffff');
    expect($loaded['footer-bg'])->toBe('#ffffff');
    expect($loaded['nav-link'])->toBe('#373c44');
    expect($loaded['nav-hover'])->toBe('#0172ad');
    expect($loaded['nav-active'])->toBe('#0172ad');
});

it('drops the six relocated colour columns off the templates table', function () {
    foreach (array_keys(ThemeColorRelocation::COLUMN_TO_TOKEN) as $column) {
        expect(Schema::hasColumn('templates', $column))->toBeFalse();
    }
    // Non-relocated inheritable columns survive.
    expect(Schema::hasColumn('templates', 'custom_scss'))->toBeTrue();
    expect(Schema::hasColumn('templates', 'header_page_id'))->toBeTrue();
});
