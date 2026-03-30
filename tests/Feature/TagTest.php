<?php

use App\Models\Contact;
use App\Models\Page;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a tag with valid data', function () {
    $tag = Tag::factory()->create([
        'name' => 'Board Member',
        'type' => 'contact',
    ]);

    expect($tag->exists)->toBeTrue()
        ->and($tag->name)->toBe('Board Member')
        ->and($tag->type)->toBe('contact');
});

it('auto-generates a slug from the tag name', function () {
    $tag = Tag::create(['name' => 'Major Donor', 'type' => 'contact']);

    expect($tag->slug)->toBe('major-donor');
});

it('enforces unique name per type', function () {
    Tag::factory()->create(['name' => 'VIP', 'type' => 'contact']);

    expect(fn () => Tag::factory()->create(['name' => 'VIP', 'type' => 'contact']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows the same name across different types', function () {
    Tag::factory()->create(['name' => 'Featured', 'type' => 'contact']);
    $postTag = Tag::factory()->create(['name' => 'Featured', 'type' => 'post']);

    expect($postTag->exists)->toBeTrue();
});

it('attaches to contacts via polymorphic relationship', function () {
    $tag = Tag::factory()->create(['type' => 'contact']);
    $contact = Contact::factory()->create();

    $tag->contacts()->attach($contact);

    expect($tag->contacts)->toHaveCount(1)
        ->and($tag->contacts->first()->id)->toBe($contact->id);
});

it('attaches to pages via polymorphic relationship', function () {
    $tag = Tag::factory()->create(['type' => 'page']);
    $page = Page::factory()->create();

    $tag->pages()->attach($page);

    expect($tag->pages)->toHaveCount(1)
        ->and($tag->pages->first()->id)->toBe($page->id);
});
