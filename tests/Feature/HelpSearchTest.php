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
});

it('returns empty results for an empty query', function () {
    HelpArticle::create([
        'slug' => 'contacts',
        'title' => 'Contacts',
        'description' => 'Managing contacts.',
        'content' => '# Contacts',
        'tags' => ['crm'],
        'category' => 'crm',
    ]);

    Livewire::test(HelpSearch::class)
        ->set('query', '')
        ->assertViewHas('results', []);
});

it('returns results matching title', function () {
    HelpArticle::create([
        'slug' => 'contacts',
        'title' => 'Contacts',
        'description' => 'Managing contacts.',
        'content' => '# Contacts',
        'tags' => ['crm'],
        'category' => 'crm',
    ]);

    HelpArticle::create([
        'slug' => 'donations',
        'title' => 'Donations',
        'description' => 'Managing donations.',
        'content' => '# Donations',
        'tags' => ['finance'],
        'category' => 'finance',
    ]);

    $component = Livewire::test(HelpSearch::class)
        ->set('query', 'Contact');

    $results = $component->get('results');
    expect($results)->toHaveCount(1)
        ->and($results[0]['slug'])->toBe('contacts');
});

it('ranks title matches above content matches', function () {
    // Article with "donations" in title
    HelpArticle::create([
        'slug' => 'donations',
        'title' => 'Donations',
        'description' => 'Managing donations.',
        'content' => '# Donations help',
        'tags' => ['finance'],
        'category' => 'finance',
    ]);

    // Article with "donations" only in content
    HelpArticle::create([
        'slug' => 'campaigns',
        'title' => 'Campaigns',
        'description' => 'Fundraising campaigns.',
        'content' => 'Campaigns track donations over time.',
        'tags' => ['finance'],
        'category' => 'finance',
    ]);

    $component = Livewire::test(HelpSearch::class)
        ->set('query', 'donations');

    $results = $component->get('results');
    expect($results)->toHaveCount(2)
        ->and($results[0]['slug'])->toBe('donations')
        ->and($results[1]['slug'])->toBe('campaigns');
});

it('respects the 8-result limit', function () {
    for ($i = 1; $i <= 10; $i++) {
        HelpArticle::create([
            'slug' => "article-{$i}",
            'title' => "Help Article {$i}",
            'description' => 'A help article.',
            'content' => 'Content here.',
            'tags' => ['help'],
            'category' => 'general',
        ]);
    }

    $component = Livewire::test(HelpSearch::class)
        ->set('query', 'Help Article');

    $results = $component->get('results');
    expect($results)->toHaveCount(8);
});

it('matches articles by tag', function () {
    HelpArticle::create([
        'slug' => 'custom-fields',
        'title' => 'Custom Fields',
        'description' => 'Define custom fields.',
        'content' => '# Custom Fields',
        'tags' => ['crm', 'custom-fields', 'contacts'],
        'category' => 'crm',
    ]);

    $component = Livewire::test(HelpSearch::class)
        ->set('query', 'custom-fields');

    $results = $component->get('results');
    expect($results)->toHaveCount(1)
        ->and($results[0]['slug'])->toBe('custom-fields');
});
