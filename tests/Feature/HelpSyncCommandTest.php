<?php

use App\Models\HelpArticle;
use App\Models\HelpArticleRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('syncs all docs files into the database', function () {
    $this->artisan('help:sync')->assertSuccessful();

    // At minimum, the contacts and dashboard articles should exist.
    expect(HelpArticle::where('slug', 'contacts')->exists())->toBeTrue()
        ->and(HelpArticle::where('slug', 'dashboard')->exists())->toBeTrue();
});

it('creates route mappings for each article', function () {
    $this->artisan('help:sync')->assertSuccessful();

    $route = HelpArticleRoute::where('route_name', 'filament.admin.resources.contacts.index')->first();

    expect($route)->not->toBeNull()
        ->and($route->article->slug)->toBe('contacts');
});

it('syncs at least 15 articles', function () {
    $this->artisan('help:sync')->assertSuccessful();

    expect(HelpArticle::count())->toBeGreaterThanOrEqual(15);
});

it('is idempotent — running twice does not duplicate articles', function () {
    $this->artisan('help:sync')->assertSuccessful();
    $countAfterFirst = HelpArticle::count();

    $this->artisan('help:sync')->assertSuccessful();
    $countAfterSecond = HelpArticle::count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});
