<?php

use App\Models\SiteSetting;
use App\Services\TypographyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('design');

it('returns a fully-populated defaults tree with concrete values', function () {
    $defaults = TypographyResolver::defaults();

    expect($defaults)->toHaveKeys(['buckets', 'elements', 'sample_text']);
    expect($defaults['buckets'])->toHaveKeys(['heading_family', 'body_family', 'nav_family']);
    expect($defaults['elements'])->toHaveKeys(TypographyResolver::ELEMENTS);
    expect($defaults['sample_text'])->toBe(TypographyResolver::DEFAULT_SAMPLE_TEXT);
    expect($defaults['elements']['h1']['font']['family'])->toBe(TypographyResolver::DEFAULT_FAMILY);
    expect($defaults['elements']['h1']['font']['weight'])->toBe('700');
    expect($defaults['elements']['p']['font']['weight'])->toBe('400');
    expect($defaults['elements']['h1']['font']['letter_spacing']['value'])->toBe(0);
    expect($defaults['elements']['h1']['font']['case'])->toBe('none');
    // Sane baseline vertical rhythm — defaults must NOT be zero-margin
    // (the product defect: a fresh install rendering headings glued to body).
    expect($defaults['elements']['h1']['margin'])->toBe(['top' => 0, 'right' => 0, 'bottom' => 1.5, 'left' => 0, 'unit' => 'rem']);
    expect($defaults['elements']['p']['margin'])->toBe(['top' => 0, 'right' => 0, 'bottom' => 1.0, 'left' => 0, 'unit' => 'rem']);
    expect($defaults['elements']['ul_li']['margin']['bottom'])->toBe(0); // deliberate: list rhythm is block-flow, not per-item
    expect($defaults['elements']['ul_li']['list_style_type'])->toBe('disc');
    expect($defaults['elements']['ol_li']['list_style_type'])->toBe('decimal');
});

it('load() preserves defaults when storage contains explicit nulls', function () {
    SiteSetting::create([
        'key'   => 'typography',
        'type'  => 'json',
        'group' => 'design',
        'value' => json_encode([
            'elements' => [
                'h1' => ['font' => ['family' => null, 'weight' => null]],
            ],
        ]),
    ]);

    $loaded = TypographyResolver::load();

    expect($loaded['elements']['h1']['font']['family'])->toBe(TypographyResolver::DEFAULT_FAMILY);
    expect($loaded['elements']['h1']['font']['weight'])->toBe('700');
});

it('load() overrides defaults with non-null stored values', function () {
    SiteSetting::create([
        'key'   => 'typography',
        'type'  => 'json',
        'group' => 'design',
        'value' => json_encode([
            'buckets'  => ['heading_family' => 'Serif, serif'],
            'elements' => ['h1' => ['font' => ['family' => 'Monospace, mono', 'weight' => '300']]],
        ]),
    ]);

    $loaded = TypographyResolver::load();

    expect($loaded['buckets']['heading_family'])->toBe('Serif, serif');
    expect($loaded['elements']['h1']['font']['family'])->toBe('Monospace, mono');
    expect($loaded['elements']['h1']['font']['weight'])->toBe('300');
});
