<?php

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

it('reaps stale export bundles and keeps fresh ones', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');

    $disk->put('exports/bundles/stale-token/old.zip', 'zip-bytes');
    $disk->put('exports/bundles/fresh-token/new.zip', 'zip-bytes');

    touch($disk->path('exports/bundles/stale-token/old.zip'), now()->subDays(3)->getTimestamp());

    $this->artisan('exports:clean', ['--hours' => 48])->assertSuccessful();

    $disk->assertMissing('exports/bundles/stale-token/old.zip');
    $disk->assertExists('exports/bundles/fresh-token/new.zip');
});

it('reaps an empty token directory', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');

    $disk->makeDirectory('exports/bundles/orphan-token');

    $this->artisan('exports:clean', ['--hours' => 48])->assertSuccessful();

    expect($disk->directories('exports/bundles'))->not->toContain('exports/bundles/orphan-token');
});

it('keeps bundles newer than the ttl', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');

    $disk->put('exports/bundles/recent-token/recent.zip', 'zip-bytes');
    touch($disk->path('exports/bundles/recent-token/recent.zip'), now()->subHours(12)->getTimestamp());

    $this->artisan('exports:clean', ['--hours' => 48])->assertSuccessful();

    $disk->assertExists('exports/bundles/recent-token/recent.zip');
});

it('is a no-op when no bundles directory exists', function () {
    Storage::fake('local');

    $this->artisan('exports:clean')->assertSuccessful();
});
