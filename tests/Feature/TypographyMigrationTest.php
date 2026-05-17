<?php

use App\Models\SiteSetting;
use App\Services\TypographyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('dropped heading_font and body_font columns from templates', function () {
    expect(Schema::hasColumn('templates', 'heading_font'))->toBeFalse();
    expect(Schema::hasColumn('templates', 'body_font'))->toBeFalse();
});

it('load() returns a defaults tree when the SiteSetting row is missing', function () {
    $typography = TypographyResolver::load();

    expect($typography)->toBeArray();
    expect($typography)->toHaveKeys(['buckets', 'elements', 'sample_text']);
    expect($typography['buckets']['heading_family'])->toBeNull();
});

/*
 * ───────────────────────────────────────────────────────────────────────────
 * Per-breakpoint font.size shape migration — G1/285 byte-exact data-loss guard
 * ───────────────────────────────────────────────────────────────────────────
 * A configured install (this install was hand-tuned at session 285) stores a
 * flat font.size = {value, unit} per element. Session 295 changes the shape to
 * { xl, lg, md, sm }. Every existing stored value MUST survive byte-exact at
 * `xl` (value AND unit, no type coercion, no rounding); lg/md/sm derive from
 * the per-class ramp. A lost or mutated tuned value is silent data loss.
 */

it('migrates a hand-tuned flat font.size to {xl,lg,md,sm} preserving every stored value byte-exact at xl', function () {
    // Mirror the 285-tuned shape: flat {value, unit}, non-default values,
    // mixed units (rem + a raw px int) to prove value AND unit AND type survive.
    $tuned = TypographyResolver::defaults();
    $tuned['elements']['h1']['font']['size'] = ['value' => 3.25,  'unit' => 'rem']; // display
    $tuned['elements']['h2']['font']['size'] = ['value' => 2.125, 'unit' => 'rem']; // section
    $tuned['elements']['h3']['font']['size'] = ['value' => 28,    'unit' => 'px'];  // section, int+px
    $tuned['elements']['h4']['font']['size'] = ['value' => 1.2,   'unit' => 'rem']; // body
    $tuned['elements']['p']['font']['size']  = ['value' => 1.05,  'unit' => 'rem']; // body

    SiteSetting::create([
        'key'   => 'typography',
        'type'  => 'json',
        'group' => 'design',
        'value' => json_encode($tuned),
    ]);

    $loaded = TypographyResolver::load();

    // Every tuned value lands byte-exact at xl — value, unit, and PHP type.
    expect($loaded['elements']['h1']['font']['size']['xl'])->toBe(['value' => 3.25,  'unit' => 'rem']);
    expect($loaded['elements']['h2']['font']['size']['xl'])->toBe(['value' => 2.125, 'unit' => 'rem']);
    expect($loaded['elements']['h3']['font']['size']['xl'])->toBe(['value' => 28,    'unit' => 'px']);
    expect($loaded['elements']['h4']['font']['size']['xl'])->toBe(['value' => 1.2,   'unit' => 'rem']);
    expect($loaded['elements']['p']['font']['size']['xl'])->toBe(['value' => 1.05,   'unit' => 'rem']);

    // The flat shape is fully replaced — not merged into a corrupt mixed shape.
    expect(array_keys($loaded['elements']['h1']['font']['size']))->toBe(['xl', 'lg', 'md', 'sm']);

    // Units propagate to the derived breakpoints unchanged.
    foreach (['lg', 'md', 'sm'] as $bp) {
        expect($loaded['elements']['h1']['font']['size'][$bp]['unit'])->toBe('rem');
        expect($loaded['elements']['h3']['font']['size'][$bp]['unit'])->toBe('px');
    }

    // display ramp (h1): lg .85, md .75, sm .60 of xl
    expect($loaded['elements']['h1']['font']['size']['lg']['value'])->toBe(2.7625);
    expect($loaded['elements']['h1']['font']['size']['md']['value'])->toBe(2.4375);
    expect($loaded['elements']['h1']['font']['size']['sm']['value'])->toBe(1.95);

    // section ramp (h2/h3): lg .93, md .88, sm .80 of xl
    expect($loaded['elements']['h2']['font']['size']['lg']['value'])->toBe(1.9763);
    expect($loaded['elements']['h2']['font']['size']['md']['value'])->toBe(1.87);
    expect($loaded['elements']['h2']['font']['size']['sm']['value'])->toBe(1.7);
    expect($loaded['elements']['h3']['font']['size']['lg']['value'])->toBe(26.04);
    expect($loaded['elements']['h3']['font']['size']['sm']['value'])->toBe(22.4);

    // body ramp (h4/p): 1.0 across the board — no scaling.
    foreach (['xl', 'lg', 'md', 'sm'] as $bp) {
        expect($loaded['elements']['h4']['font']['size'][$bp]['value'])->toBe(1.2);
        expect($loaded['elements']['p']['font']['size'][$bp]['value'])->toBe(1.05);
    }
});

it('is idempotent: an already per-breakpoint stored size is not re-derived (user-tuned lg/md/sm survive)', function () {
    $state = TypographyResolver::defaults();
    $state['elements']['h1']['font']['size'] = [
        'xl' => ['value' => 3.1, 'unit' => 'rem'],
        'lg' => ['value' => 2.9, 'unit' => 'rem'], // deliberately NOT the ramp value
        'md' => ['value' => 2.8, 'unit' => 'rem'],
        'sm' => ['value' => 2.7, 'unit' => 'rem'],
    ];

    SiteSetting::create([
        'key'   => 'typography',
        'type'  => 'json',
        'group' => 'design',
        'value' => json_encode($state),
    ]);

    $loaded = TypographyResolver::load();

    expect($loaded['elements']['h1']['font']['size'])->toBe([
        'xl' => ['value' => 3.1, 'unit' => 'rem'],
        'lg' => ['value' => 2.9, 'unit' => 'rem'],
        'md' => ['value' => 2.8, 'unit' => 'rem'],
        'sm' => ['value' => 2.7, 'unit' => 'rem'],
    ]);
});

it('does not rewrite the stored SiteSetting row on read (non-destructive migration)', function () {
    $tuned = TypographyResolver::defaults();
    $tuned['elements']['h1']['font']['size'] = ['value' => 3.25, 'unit' => 'rem'];
    $original = json_encode($tuned);

    SiteSetting::create([
        'key'   => 'typography',
        'type'  => 'json',
        'group' => 'design',
        'value' => $original,
    ]);

    TypographyResolver::load();

    expect(SiteSetting::where('key', 'typography')->first()->value)->toBe($original);
});

it('derives a full per-breakpoint set for an unconfigured element (concrete-value rule, no flat-by-omission)', function () {
    $defaults = TypographyResolver::defaults();

    foreach (TypographyResolver::ELEMENTS as $el) {
        $size = $defaults['elements'][$el]['font']['size'];
        expect(array_keys($size))->toBe(['xl', 'lg', 'md', 'sm']);
        foreach (['xl', 'lg', 'md', 'sm'] as $bp) {
            expect($size[$bp])->toHaveKeys(['value', 'unit']);
            expect($size[$bp]['value'])->toBeNumeric();
        }
    }

    // h1 default 2.5rem → display ramp; body elements flat at 1.0×.
    expect($defaults['elements']['h1']['font']['size']['xl']['value'])->toBe(2.5);
    expect($defaults['elements']['h1']['font']['size']['sm']['value'])->toBe(1.5);   // 2.5 × 0.60
    expect($defaults['elements']['p']['font']['size']['sm']['value'])
        ->toBe($defaults['elements']['p']['font']['size']['xl']['value']);            // body: no scaling
});

it('preserves a configured rem margin scheme through load() (no em-rhythm rewrite, no sweep)', function () {
    // Mirror this install's real shape: a deliberate rem vertical-rhythm
    // scheme with a per-box `unit`. None of it is "stale" — it must survive.
    $tuned = TypographyResolver::defaults();
    $tuned['elements']['h1']['margin'] = ['top' => 0, 'right' => 0, 'bottom' => 1.5, 'left' => 0, 'unit' => 'rem'];
    $tuned['elements']['p']['margin']  = ['top' => 0, 'right' => 0, 'bottom' => 1,   'left' => 0, 'unit' => 'rem'];

    SiteSetting::create([
        'key'   => 'typography',
        'type'  => 'json',
        'group' => 'design',
        'value' => json_encode($tuned),
    ]);

    $loaded = TypographyResolver::load();

    expect($loaded['elements']['h1']['margin'])->toBe(['top' => 0, 'right' => 0, 'bottom' => 1.5, 'left' => 0, 'unit' => 'rem']);
    expect($loaded['elements']['p']['margin']['bottom'])->toBe(1);
    expect($loaded['elements']['h1'])->not->toHaveKey('margin_em');
});
