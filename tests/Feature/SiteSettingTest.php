<?php

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns the default value when key does not exist', function () {
    expect(SiteSetting::get('nonexistent_key', 'default_value'))->toBe('default_value');
});

it('returns null as the default when no default is provided', function () {
    expect(SiteSetting::get('nonexistent_key'))->toBeNull();
});

it('returns the value from the database when key exists', function () {
    SiteSetting::create([
        'key'   => 'site_name',
        'value' => 'Test Org',
        'group' => 'general',
        'type'  => 'string',
    ]);

    expect(SiteSetting::get('site_name'))->toBe('Test Org');
});

it('casts boolean values correctly', function () {
    SiteSetting::create([
        'key'   => 'use_pico',
        'value' => 'true',
        'group' => 'styles',
        'type'  => 'boolean',
    ]);

    expect(SiteSetting::get('use_pico'))->toBeTrue();

    Cache::forget('site_setting:use_pico');

    SiteSetting::where('key', 'use_pico')->update(['value' => 'false']);

    expect(SiteSetting::get('use_pico'))->toBeFalse();
});

it('casts integer values correctly', function () {
    SiteSetting::create([
        'key'   => 'some_count',
        'value' => '42',
        'group' => 'general',
        'type'  => 'integer',
    ]);

    expect(SiteSetting::get('some_count'))->toBe(42);
});

it('set() writes the value and invalidates the cache', function () {
    SiteSetting::create([
        'key'   => 'site_name',
        'value' => 'Old Name',
        'group' => 'general',
        'type'  => 'string',
    ]);

    // Prime the cache
    SiteSetting::get('site_name');
    expect(Cache::has('site_setting:site_name'))->toBeTrue();

    // Update via set()
    SiteSetting::set('site_name', 'New Name');

    // Cache should be gone
    expect(Cache::has('site_setting:site_name'))->toBeFalse();

    // Fresh read should return new value
    expect(SiteSetting::get('site_name'))->toBe('New Name');
});

it('set() creates a new record if key does not exist', function () {
    SiteSetting::set('brand_new_key', 'brand_new_value');

    expect(SiteSetting::where('key', 'brand_new_key')->value('value'))->toBe('brand_new_value');
});
