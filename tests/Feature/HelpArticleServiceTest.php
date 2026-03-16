<?php

use App\Models\HelpArticle;
use App\Models\HelpArticleRoute;
use App\Services\HelpArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns the article for a known route name', function () {
    $article = HelpArticle::create([
        'slug'        => 'contacts',
        'title'       => 'Contacts',
        'description' => 'Managing contacts.',
        'content'     => '# Contacts',
        'tags'        => ['crm'],
    ]);

    HelpArticleRoute::create([
        'help_article_id' => $article->id,
        'route_name'      => 'filament.admin.resources.contacts.index',
    ]);

    $service = app(HelpArticleService::class);
    $result = $service->forRoute('filament.admin.resources.contacts.index');

    expect($result)->toBeInstanceOf(HelpArticle::class)
        ->and($result->slug)->toBe('contacts');
});

it('returns null for an unknown route name', function () {
    $service = app(HelpArticleService::class);
    $result = $service->forRoute('filament.admin.resources.nonexistent.index');

    expect($result)->toBeNull();
});

it('returns null when no articles exist', function () {
    $service = app(HelpArticleService::class);
    $result = $service->forRoute('filament.admin.pages.dashboard');

    expect($result)->toBeNull();
});
