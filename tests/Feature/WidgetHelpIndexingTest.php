<?php

use App\Livewire\HelpSearch;
use App\Models\HelpArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = \App\Models\User::factory()->create();
    $this->actingAs($this->user);
    $this->artisan('help:sync')->assertSuccessful();
});

it('indexes the canonical widgets article and the five widget help articles after help:sync', function () {
    $expected = [
        'widgets',
        'widget-bar-chart',
        'widget-donation-form',
        'widget-event-calendar',
        'widget-event-registration',
        'widget-web-form',
    ];

    foreach ($expected as $slug) {
        expect(HelpArticle::where('slug', $slug)->exists())
            ->toBeTrue("expected help article '{$slug}' to be indexed after help:sync");
    }
});

it('returns the bar-chart widget article when searching for "bar chart"', function () {
    $component = Livewire::test(HelpSearch::class)
        ->set('query', 'bar chart');

    $slugs = collect($component->get('results'))->pluck('slug')->all();

    expect($slugs)->toContain('widget-bar-chart');
});

it('returns the donation-form widget article when searching for "donation form"', function () {
    $component = Livewire::test(HelpSearch::class)
        ->set('query', 'donation form');

    $slugs = collect($component->get('results'))->pluck('slug')->all();

    expect($slugs)->toContain('widget-donation-form');
});

it('ranks the canonical Widgets article first when searching for "widget"', function () {
    $component = Livewire::test(HelpSearch::class)
        ->set('query', 'widget');

    $slugs = collect($component->get('results'))->pluck('slug')->all();

    expect($slugs[0])->toBe('widgets');
});

it('persists the search_weight from frontmatter into help_articles', function () {
    expect(HelpArticle::where('slug', 'widgets')->value('search_weight'))->toBe(100)
        ->and(HelpArticle::where('slug', 'widget-bar-chart')->value('search_weight'))->toBe(0);
});

it('persists parent_slug from frontmatter and resolves parent() to the canonical Widgets article', function () {
    $barChart = HelpArticle::where('slug', 'widget-bar-chart')->first();

    expect($barChart->parent_slug)->toBe('widgets')
        ->and($barChart->parent()?->slug)->toBe('widgets')
        ->and(HelpArticle::where('slug', 'widgets')->value('parent_slug'))->toBeNull();
});

it('renders the breadcrumb chain Help > CMS > Widgets > Bar Chart Widget for widget-bar-chart', function () {
    $page = new \App\Filament\Pages\HelpArticlePage();
    $page->article = HelpArticle::where('slug', 'widget-bar-chart')->first();

    $titles = array_values($page->getBreadcrumbs());

    expect($titles)->toBe(['Help', 'CMS', 'Widgets', 'Bar Chart Widget']);
});
