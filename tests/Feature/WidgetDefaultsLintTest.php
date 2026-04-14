<?php

use App\Services\WidgetRegistry;
use Tests\TestCase;

uses(TestCase::class);

it('every defaults() value has a PHP type matching its schema field type', function () {
    $registry = app(WidgetRegistry::class);
    expect($registry->all())->not->toBeEmpty();

    foreach ($registry->all() as $def) {
        $defaults = $def->defaults();
        foreach ($def->schema() as $field) {
            $key = $field['key'] ?? null;
            if ($key === null) continue;
            if (! array_key_exists($key, $defaults)) continue;

            $type = $field['type'] ?? 'text';
            $value = $defaults[$key];
            $handle = $def->handle();

            switch ($type) {
                case 'toggle':
                    expect(is_bool($value))->toBeTrue(
                        "Widget [{$handle}] defaults[{$key}] must be bool for toggle field, got " . gettype($value)
                    );
                    break;
                case 'number':
                    expect($value === null || is_int($value) || is_float($value))->toBeTrue(
                        "Widget [{$handle}] defaults[{$key}] must be int|float|null for number field, got " . gettype($value)
                    );
                    break;
                case 'image':
                case 'video':
                    expect($value === null || is_int($value) || is_string($value))->toBeTrue(
                        "Widget [{$handle}] defaults[{$key}] must be null|int|string for {$type} field, got " . gettype($value)
                    );
                    break;
                case 'checkboxes':
                    expect(is_array($value))->toBeTrue(
                        "Widget [{$handle}] defaults[{$key}] must be array for checkboxes field, got " . gettype($value)
                    );
                    break;
                case 'buttons':
                    expect(is_array($value) || is_string($value))->toBeTrue(
                        "Widget [{$handle}] defaults[{$key}] must be array|string for buttons field, got " . gettype($value)
                    );
                    break;
                case 'gradient':
                    expect($value === null || is_array($value))->toBeTrue(
                        "Widget [{$handle}] defaults[{$key}] must be null|array for gradient field, got " . gettype($value)
                    );
                    break;
                default:
                    expect(is_string($value))->toBeTrue(
                        "Widget [{$handle}] defaults[{$key}] must be string for {$type} field, got " . gettype($value)
                    );
            }
        }
    }
})->group('widget-lint');

it('every select field default is one of the declared option keys', function () {
    $registry = app(WidgetRegistry::class);
    expect($registry->all())->not->toBeEmpty();

    foreach ($registry->all() as $def) {
        foreach ($def->schema() as $field) {
            if (($field['type'] ?? null) !== 'select') continue;
            if (! array_key_exists('default', $field)) continue;
            if (! isset($field['options']) || ! is_array($field['options'])) continue;

            $default = $field['default'];
            // PHP auto-casts numeric string array keys to ints, so compare loosely against stringified keys.
            $optionKeys = array_map(fn ($k) => (string) $k, array_keys($field['options']));
            $key = $field['key'] ?? '(unknown)';
            $handle = $def->handle();

            expect(in_array((string) $default, $optionKeys, true))->toBeTrue(
                "Widget [{$handle}] select field [{$key}] default '{$default}' not in options: " . implode(', ', $optionKeys)
            );
        }
    }
})->group('widget-lint');

it('every defaults() key appears in schema() (no orphans)', function () {
    $registry = app(WidgetRegistry::class);
    expect($registry->all())->not->toBeEmpty();

    foreach ($registry->all() as $def) {
        $schemaKeys = collect($def->schema())->pluck('key')->filter()->all();
        $handle = $def->handle();

        foreach (array_keys($def->defaults()) as $key) {
            expect(in_array($key, $schemaKeys, true))->toBeTrue(
                "Widget [{$handle}] defaults() has orphan key not in schema(): {$key}"
            );
        }
    }
})->group('widget-lint');

it('detects a toggle-with-string-default as a lint failure', function () {
    $def = new class extends \App\Widgets\Contracts\WidgetDefinition {
        public function handle(): string { return 'broken'; }
        public function label(): string { return 'Broken'; }
        public function description(): string { return ''; }
        public function schema(): array { return [['key' => 'flag', 'type' => 'toggle']]; }
        public function defaults(): array { return ['flag' => 'yes']; }
    };

    $value = $def->defaults()['flag'];
    expect(is_bool($value))->toBeFalse();
});
