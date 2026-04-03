<?php

use App\Models\HelpArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('syncs category from frontmatter', function () {
    $this->artisan('help:sync')->assertSuccessful();

    $article = HelpArticle::where('slug', 'contacts')->first();

    expect($article)->not->toBeNull()
        ->and($article->category)->toBe('crm');
});

it('stores correct categories for known articles', function () {
    $this->artisan('help:sync')->assertSuccessful();

    $expectedCategories = [
        'contacts' => 'crm',
        'cms-pages' => 'cms',
        'donations' => 'finance',
        'importer' => 'tools',
        'settings-general' => 'settings',
        'dashboard' => 'general',
    ];

    foreach ($expectedCategories as $slug => $category) {
        $article = HelpArticle::where('slug', $slug)->first();
        expect($article)->not->toBeNull("Article '{$slug}' should exist")
            ->and($article->category)->toBe($category, "Article '{$slug}' should have category '{$category}'");
    }
});

it('assigns a category to every synced article', function () {
    $this->artisan('help:sync')->assertSuccessful();

    $withoutCategory = HelpArticle::whereNull('category')->count();

    expect($withoutCategory)->toBe(0, 'All articles should have a category');
});

it('only uses valid category values', function () {
    $this->artisan('help:sync')->assertSuccessful();

    $validCategories = ['crm', 'cms', 'finance', 'tools', 'settings', 'general'];

    $articles = HelpArticle::whereNotNull('category')->pluck('category')->unique()->values()->toArray();

    foreach ($articles as $cat) {
        expect($validCategories)->toContain($cat);
    }
});
