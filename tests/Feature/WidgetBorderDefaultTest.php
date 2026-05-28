<?php

// Session 323 — the universal border ships as a concrete appearance default.
// Every registered widget's defaultAppearanceConfig() must carry a complete,
// concrete border block (all sides off, width/radius 0, a default hex color) so
// no widget relies on a missing key as a proxy for "no border".

use App\Services\WidgetRegistry;
use Tests\TestCase;

uses(TestCase::class);

it('base WidgetDefinition default carries a concrete all-off border block', function () {
    $def = new class extends \App\Widgets\Contracts\WidgetDefinition {
        public function handle(): string { return 'border_default_probe'; }
        public function label(): string { return 'Probe'; }
        public function description(): string { return ''; }
        public function schema(): array { return []; }
        public function defaults(): array { return []; }
    };

    $border = $def->defaultAppearanceConfig()['layout']['border'] ?? null;

    expect($border)->toBe([
        'top'    => false,
        'right'  => false,
        'bottom' => false,
        'left'   => false,
        'width'  => 0,
        'color'  => '#000000',
        'radius' => 0,
    ]);
});

it('every registered widget default carries a concrete border block', function () {
    /** @var WidgetRegistry $registry */
    $registry = app(WidgetRegistry::class);

    foreach ($registry->all() as $def) {
        $border = $def->defaultAppearanceConfig()['layout']['border'] ?? null;

        expect($border)->toBeArray("Widget [{$def->handle()}] is missing a concrete border block");

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            expect($border[$side])->toBeBool("Widget [{$def->handle()}] border.{$side} must be a concrete bool");
        }
        expect($border['width'])->toBeInt();
        expect($border['radius'])->toBeInt();
        expect($border['color'])->toMatch('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/');
    }
});
