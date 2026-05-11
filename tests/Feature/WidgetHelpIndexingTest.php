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

it('indexes the widget catalog and the five widget help articles after help:sync', function () {
    $expected = [
        'widget-catalog',
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

    expect(HelpArticle::where('slug', 'like', 'widget-%')->count())
        ->toBeGreaterThanOrEqual(6);
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
