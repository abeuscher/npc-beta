<?php

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
