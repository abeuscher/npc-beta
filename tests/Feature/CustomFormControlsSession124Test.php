<?php

use App\Support\FormFieldConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── FormFieldConfig ──────────────────────────────────────────────────────────

it('resolves width from handle lookup', function () {
    FormFieldConfig::flush();

    expect(FormFieldConfig::width('first_name'))->toBe(6);
    expect(FormFieldConfig::width('city'))->toBe(5);
    expect(FormFieldConfig::width('state'))->toBe(4);
    expect(FormFieldConfig::width('zip'))->toBe(3);
});

it('returns default width for unknown handles', function () {
    FormFieldConfig::flush();

    expect(FormFieldConfig::width('some_random_field'))->toBe(12);
});

it('explicit width overrides config lookup', function () {
    FormFieldConfig::flush();

    // first_name would normally be 6, but explicit 8 wins
    expect(FormFieldConfig::width('first_name', null, 8))->toBe(8);
});

it('matches rules by label when handle is not in widths', function () {
    FormFieldConfig::flush();

    // Write a temporary config with a rule
    $config = FormFieldConfig::load();
    $config['rules'] = [
        ['match' => ['label' => 'Favourite Colour'], 'set' => ['width' => 4]],
    ];

    // Use reflection to inject the config
    $ref = new ReflectionClass(FormFieldConfig::class);
    $prop = $ref->getProperty('config');
    $prop->setValue(null, $config);

    expect(FormFieldConfig::width('favourite_colour', 'Favourite Colour'))->toBe(4);
    expect(FormFieldConfig::width('favourite_colour', 'Other Label'))->toBe(12);

    FormFieldConfig::flush();
});

// ── Blade component rendering ────────────────────────────────────────────────

it('custom-select renders hidden native select with correct name and options', function () {
    $html = Blade::render('<x-custom-select name="color" :options="$opts" />', [
        'opts' => [
            ['value' => 'red', 'label' => 'Red'],
            ['value' => 'blue', 'label' => 'Blue'],
        ],
    ]);

    expect($html)
        ->toContain('name="color"')
        ->toContain('<option')
        ->toContain('value="red"')
        ->toContain('value="blue"')
        ->toContain('role="combobox"');
});

it('custom-select pre-selects the given value', function () {
    $html = Blade::render('<x-custom-select name="size" :options="$opts" value="lg" />', [
        'opts' => [
            ['value' => 'sm', 'label' => 'Small'],
            ['value' => 'lg', 'label' => 'Large'],
        ],
    ]);

    // The native select should have the option marked as selected
    expect($html)->toMatch('/value="lg"\s*selected/');
});

it('custom-select renders search input when searchable', function () {
    $html = Blade::render('<x-custom-select name="q" :options="$opts" :searchable="true" />', [
        'opts' => [['value' => 'a', 'label' => 'Alpha']],
    ]);

    expect($html)
        ->toContain('custom-select__search')
        ->toContain('placeholder="Search…"');
});

it('custom-select does not render search input by default', function () {
    $html = Blade::render('<x-custom-select name="q" :options="$opts" />', [
        'opts' => [['value' => 'a', 'label' => 'Alpha']],
    ]);

    expect($html)->not->toContain('custom-select__search');
});

it('toggle renders a checkbox with role switch', function () {
    $html = Blade::render('<x-toggle name="dark_mode" label="Dark mode" />');

    expect($html)
        ->toContain('name="dark_mode"')
        ->toContain('role="switch"')
        ->toContain('toggle__slider')
        ->toContain('Dark mode');
});

it('toggle renders without a name for non-form usage', function () {
    $html = Blade::render('<x-toggle alpine-checked="true" alpine-change="doStuff()" />');

    expect($html)
        ->toContain('role="switch"')
        ->toContain(':checked="true"')
        ->toContain('@change="doStuff()"')
        ->not->toContain('name=');
});

it('radio-group renders buttons with role radio and hidden input', function () {
    $html = Blade::render('<x-radio-group name="freq" :options="$opts" />', [
        'opts' => [
            ['value' => 'daily', 'label' => 'Daily'],
            ['value' => 'weekly', 'label' => 'Weekly'],
        ],
    ]);

    expect($html)
        ->toContain('name="freq"')
        ->toContain('role="radio"')
        ->toContain('role="radiogroup"')
        ->toContain('Daily')
        ->toContain('Weekly');
});

it('radio-group applies stacked layout class', function () {
    $html = Blade::render('<x-radio-group name="x" :options="$opts" layout="stacked" />', [
        'opts' => [['value' => 'a', 'label' => 'A']],
    ]);

    expect($html)->toContain('radio-group--stacked');
});

it('state-select renders all 50 states plus DC and territories', function () {
    $html = Blade::render('<x-state-select name="state" />');

    // Spot-check a few states and territories
    expect($html)
        ->toContain('name="state"')
        ->toContain('California')
        ->toContain('New York')
        ->toContain('Wyoming')
        ->toContain('District of Columbia')
        ->toContain('Puerto Rico')
        ->toContain('Guam');
});

it('state-select is searchable by default', function () {
    $html = Blade::render('<x-state-select name="state" />');

    expect($html)->toContain('custom-select__search');
});

// ── Config file integrity ────────────────────────────────────────────────────

it('form-fields.json is valid JSON with required keys', function () {
    $path = config_path('form-fields.json');
    expect(file_exists($path))->toBeTrue();

    $config = json_decode(file_get_contents($path), true);
    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($config)->toHaveKeys(['widths', 'rules', 'default_width']);
    expect($config['widths'])->toBeArray();
    expect($config['rules'])->toBeArray();
    expect($config['default_width'])->toBeInt();
});

// ── Asset build includes controls partial ────────────────────────────────────

it('asset build collects controls partial in scss sources', function () {
    $service = app(\App\Services\AssetBuildService::class);
    $method = new ReflectionMethod($service, 'collectSources');
    $sources = $method->invoke($service);

    $themeContent = $sources['scss'][0]['content'];

    expect($themeContent)
        ->toContain('.custom-select')
        ->toContain('.toggle__slider')
        ->toContain('.radio-group');
});
