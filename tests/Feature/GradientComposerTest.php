<?php

use App\Services\GradientComposer;

beforeEach(function () {
    $this->composer = new GradientComposer();
});

// ── Empty / null cases ───────────────────────────────────────────────────────

it('returns blank for null input', function () {
    expect($this->composer->compose(null))->toBe('');
});

it('returns blank for empty array', function () {
    expect($this->composer->compose([]))->toBe('');
});

it('returns blank when gradients key is missing', function () {
    expect($this->composer->compose(['unrelated' => 'value']))->toBe('');
});

it('returns blank when gradients array is empty', function () {
    expect($this->composer->compose(['gradients' => []]))->toBe('');
});

it('blank() helper returns empty string', function () {
    expect($this->composer->blank())->toBe('');
});

// ── Single linear gradient ───────────────────────────────────────────────────

it('composes a single linear gradient with the default angle', function () {
    $css = $this->composer->compose([
        'gradients' => [
            ['type' => 'linear', 'from' => '#ffffff', 'to' => '#000000'],
        ],
    ]);

    expect($css)->toBe('linear-gradient(180deg, #ffffff, #000000)');
});

it('composes a single linear gradient with a custom angle', function () {
    $css = $this->composer->compose([
        'gradients' => [
            ['type' => 'linear', 'from' => '#ff0000', 'to' => '#00ff00', 'angle' => 45],
        ],
    ]);

    expect($css)->toBe('linear-gradient(45deg, #ff0000, #00ff00)');
});

it('composes a single radial gradient (angle ignored)', function () {
    $css = $this->composer->compose([
        'gradients' => [
            ['type' => 'radial', 'from' => '#abcdef', 'to' => '#123456', 'angle' => 90],
        ],
    ]);

    expect($css)->toBe('radial-gradient(#abcdef, #123456)');
});

it('lowercases hex values in the output', function () {
    $css = $this->composer->compose([
        'gradients' => [
            ['type' => 'linear', 'from' => '#FFAA00', 'to' => '#00BBCC', 'angle' => 90],
        ],
    ]);

    expect($css)->toBe('linear-gradient(90deg, #ffaa00, #00bbcc)');
});

it('accepts 3-character hex shorthand', function () {
    $css = $this->composer->compose([
        'gradients' => [
            ['type' => 'linear', 'from' => '#fff', 'to' => '#000'],
        ],
    ]);

    expect($css)->toBe('linear-gradient(180deg, #fff, #000)');
});

// ── Dual-gradient stack ──────────────────────────────────────────────────────

it('stacks two gradients with the second layer painted on top', function () {
    $css = $this->composer->compose([
        'gradients' => [
            ['type' => 'linear', 'from' => '#111111', 'to' => '#222222', 'angle' => 0],
            ['type' => 'linear', 'from' => '#aaaaaa', 'to' => '#bbbbbb', 'angle' => 90],
        ],
    ]);

    // Second layer first in CSS string, since first listed paints on top.
    expect($css)->toBe(
        'linear-gradient(90deg, #aaaaaa, #bbbbbb), linear-gradient(0deg, #111111, #222222)'
    );
});

it('stacks a linear and a radial gradient', function () {
    $css = $this->composer->compose([
        'gradients' => [
            ['type' => 'linear', 'from' => '#ffffff', 'to' => '#000000'],
            ['type' => 'radial', 'from' => '#ff0000', 'to' => '#0000ff'],
        ],
    ]);

    expect($css)->toBe(
        'radial-gradient(#ff0000, #0000ff), linear-gradient(180deg, #ffffff, #000000)'
    );
});

// ── CSS override path ────────────────────────────────────────────────────────

it('uses css_override when provided and well-formed', function () {
    $css = $this->composer->compose([
        'gradients' => [
            [
                'type' => 'linear',
                'from' => '#000000',
                'to'   => '#ffffff',
                'css_override' => 'linear-gradient(45deg, #ff0000, #00ff00)',
            ],
        ],
    ]);

    expect($css)->toBe('linear-gradient(45deg, #ff0000, #00ff00)');
});

it('rejects css_override containing url() and falls through to dropping the layer', function () {
    $css = $this->composer->compose([
        'gradients' => [
            [
                'type' => 'linear',
                'from' => '#000000',
                'to'   => '#ffffff',
                'css_override' => 'url(http://evil.example/x.png)',
            ],
        ],
    ]);

    expect($css)->toBe('');
});

it('rejects css_override containing expression() syntax', function () {
    $css = $this->composer->compose([
        'gradients' => [
            [
                'type' => 'linear',
                'from' => '#000000',
                'to'   => '#ffffff',
                'css_override' => 'expression(alert(1))',
            ],
        ],
    ]);

    expect($css)->toBe('');
});

it('rejects css_override with non-hex colour syntax', function () {
    $css = $this->composer->compose([
        'gradients' => [
            [
                'type' => 'linear',
                'from' => '#000000',
                'to'   => '#ffffff',
                'css_override' => 'linear-gradient(45deg, rgb(255,0,0), rgb(0,255,0))',
            ],
        ],
    ]);

    expect($css)->toBe('');
});

// ── Malformed input rejection ────────────────────────────────────────────────

it('drops a layer with a non-hex from value', function () {
    $css = $this->composer->compose([
        'gradients' => [
            ['type' => 'linear', 'from' => 'red', 'to' => '#000000'],
        ],
    ]);

    expect($css)->toBe('');
});

it('drops a layer with a missing colour value', function () {
    $css = $this->composer->compose([
        'gradients' => [
            ['type' => 'linear', 'from' => '#ffffff'],
        ],
    ]);

    expect($css)->toBe('');
});

it('drops a layer with an unknown gradient type', function () {
    $css = $this->composer->compose([
        'gradients' => [
            ['type' => 'conic', 'from' => '#ffffff', 'to' => '#000000'],
        ],
    ]);

    expect($css)->toBe('');
});

it('clamps an out-of-range angle to the default', function () {
    $css = $this->composer->compose([
        'gradients' => [
            ['type' => 'linear', 'from' => '#ffffff', 'to' => '#000000', 'angle' => 999],
        ],
    ]);

    expect($css)->toBe('linear-gradient(180deg, #ffffff, #000000)');
});

it('clamps a negative angle to the default', function () {
    $css = $this->composer->compose([
        'gradients' => [
            ['type' => 'linear', 'from' => '#ffffff', 'to' => '#000000', 'angle' => -45],
        ],
    ]);

    expect($css)->toBe('linear-gradient(180deg, #ffffff, #000000)');
});

it('keeps valid layers and drops malformed ones in a mixed stack', function () {
    $css = $this->composer->compose([
        'gradients' => [
            ['type' => 'linear', 'from' => '#ffffff', 'to' => '#000000'],
            ['type' => 'bogus',  'from' => '#ff0000', 'to' => '#00ff00'],
        ],
    ]);

    // Only the first (valid) layer survives, rendered as the only layer.
    expect($css)->toBe('linear-gradient(180deg, #ffffff, #000000)');
});

it('skips non-array entries in the gradients array', function () {
    $css = $this->composer->compose([
        'gradients' => [
            'not-an-array',
            ['type' => 'linear', 'from' => '#ffffff', 'to' => '#000000'],
        ],
    ]);

    expect($css)->toBe('linear-gradient(180deg, #ffffff, #000000)');
});
