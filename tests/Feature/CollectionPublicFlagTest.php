<?php

use App\Models\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('scopePublic does not return a private collection', function () {
    Collection::create([
        'name'        => 'Private Collection',
        'handle'        => 'private-collection',
        'source_type' => 'custom',
        'is_public'   => false,
        'is_active'   => true,
        'fields'      => [],
    ]);

    expect(Collection::public()->count())->toBe(0);
});

it('scopePublic returns a public and active collection', function () {
    Collection::create([
        'name'        => 'Public Collection',
        'handle'        => 'public-collection',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [],
    ]);

    expect(Collection::public()->count())->toBe(1);
});

it('scopePublic does not return a public but inactive collection', function () {
    Collection::create([
        'name'        => 'Inactive Public Collection',
        'handle'        => 'inactive-public',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => false,
        'fields'      => [],
    ]);

    expect(Collection::public()->count())->toBe(0);
});
