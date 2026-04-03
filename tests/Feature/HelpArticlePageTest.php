<?php

use App\Models\HelpArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = \App\Models\User::factory()->create();
    $this->actingAs($this->user);
});

it('renders a help article page', function () {
    HelpArticle::create([
        'slug' => 'contacts',
        'title' => 'Contacts',
        'description' => 'Managing contacts.',
        'content' => '# Contacts',
        'tags' => ['crm'],
        'category' => 'crm',
        'app_version' => '0.26',
        'last_updated' => '2026-04-01',
    ]);

    $response = $this->get('/admin/help/contacts');

    $response->assertSuccessful()
        ->assertSeeText('Contacts');
});

it('returns 404 for unknown slugs', function () {
    $response = $this->get('/admin/help/nonexistent-article');

    $response->assertStatus(404);
});

it('shows related articles based on tag overlap', function () {
    $main = HelpArticle::create([
        'slug' => 'contacts',
        'title' => 'Contacts',
        'description' => 'Managing contacts.',
        'content' => '# Contacts',
        'tags' => ['crm', 'tags', 'custom-fields'],
        'category' => 'crm',
    ]);

    $related = HelpArticle::create([
        'slug' => 'tags',
        'title' => 'Tags',
        'description' => 'Managing tags.',
        'content' => '# Tags',
        'tags' => ['crm', 'tags'],
        'category' => 'crm',
    ]);

    $unrelated = HelpArticle::create([
        'slug' => 'donations',
        'title' => 'Donations',
        'description' => 'Managing donations.',
        'content' => '# Donations',
        'tags' => ['finance'],
        'category' => 'finance',
    ]);

    $response = $this->get('/admin/help/contacts');

    $response->assertSuccessful()
        ->assertSeeText('Tags')
        ->assertDontSeeText('Donations');
});

it('requires authentication', function () {
    auth()->logout();

    HelpArticle::create([
        'slug' => 'contacts',
        'title' => 'Contacts',
        'description' => 'Managing contacts.',
        'content' => '# Contacts',
        'tags' => ['crm'],
        'category' => 'crm',
    ]);

    $response = $this->get('/admin/help/contacts');

    $response->assertRedirect();
});
