<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

it('refuses to run on production', function () {
    $this->app['env'] = 'production';

    $exit = Artisan::call('app:reset');

    expect($exit)->not->toBe(0);
    expect(Artisan::output())->toContain('cannot run in production');
});

it('wipes the public storage tree and reseeds the database', function () {
    Storage::fake('public');

    Storage::disk('public')->put('1/marker.png', 'fake image data');
    Storage::disk('public')->put('2/conversions/marker-thumb.webp', 'fake conversion data');
    Storage::disk('public')->put('orphan-at-root.txt', 'fake orphan');

    $exit = Artisan::call('app:reset');

    expect($exit)->toBe(0);
    expect(Storage::disk('public')->files())->toBeEmpty();
    expect(Storage::disk('public')->directories())->toBeEmpty();
})->group('slow');
