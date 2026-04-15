<?php

use App\Models\Event;
use App\Models\Page;
use App\Models\User;
use App\Services\PageContextTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tokens = new PageContextTokens();
});

it('returns text unchanged when page is null', function () {
    expect($this->tokens->substitute('{{title}}', null))->toBe('{{title}}');
});

it('returns text unchanged when no tokens present', function () {
    $page = Page::factory()->create(['title' => 'Hello']);
    expect($this->tokens->substitute('Plain text', $page))->toBe('Plain text');
});

it('substitutes the title token', function () {
    $page = Page::factory()->create(['title' => 'My Post']);
    expect($this->tokens->substitute('Welcome to {{title}}', $page))->toBe('Welcome to My Post');
});

it('substitutes the date token when published_at is set', function () {
    $page = Page::factory()->create([
        'title'        => 'Dated',
        'published_at' => '2026-03-15 10:00:00',
    ]);
    expect($this->tokens->substitute('{{date}}', $page))->toBe('March 15, 2026');
});

it('yields empty string for date when published_at is null', function () {
    $page = Page::factory()->create(['title' => 'Draft', 'published_at' => null]);
    expect($this->tokens->substitute('[{{date}}]', $page))->toBe('[]');
});

it('substitutes the author token from the page author relation', function () {
    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    $page = Page::factory()->create(['title' => 'Post', 'author_id' => $author->id]);
    expect($this->tokens->substitute('By {{author}}', $page))->toBe('By Ada Lovelace');
});

it('substitutes the excerpt token from meta_description', function () {
    $page = Page::factory()->create(['meta_description' => 'A short blurb.']);
    expect($this->tokens->substitute('{{excerpt}}', $page))->toBe('A short blurb.');
});

it('leaves unknown tokens untouched', function () {
    $page = Page::factory()->create(['title' => 'Hello']);
    expect($this->tokens->substitute('{{title}} / {{nope}}', $page))->toBe('Hello / {{nope}}');
});

it('substitutes event-specific tokens when the page has an associated event', function () {
    $page = Page::factory()->create(['title' => 'Gala', 'type' => 'event']);

    Event::factory()->create([
        'title'           => 'Gala',
        'landing_page_id' => $page->id,
        'starts_at'       => '2026-05-20 18:30:00',
        'city'            => 'Portland',
        'state'           => 'OR',
    ]);

    $page->refresh();

    expect($this->tokens->substitute('{{starts_at}} in {{location}}', $page))
        ->toBe('May 20, 2026 6:30 pm in Portland, OR');
});

it('yields empty event tokens when the page has no event', function () {
    $page = Page::factory()->create(['title' => 'Plain']);
    expect($this->tokens->substitute('[{{starts_at}}][{{location}}]', $page))->toBe('[][]');
});

it('html-escapes substituted values when escape flag is set', function () {
    $page = Page::factory()->create(['title' => 'Tom & Jerry <script>']);
    expect($this->tokens->substitute('{{title}}', $page, true))
        ->toBe('Tom &amp; Jerry &lt;script&gt;');
});

it('leaves substituted values raw when escape flag is not set', function () {
    $page = Page::factory()->create(['title' => 'Tom & Jerry']);
    expect($this->tokens->substitute('{{title}}', $page, false))
        ->toBe('Tom & Jerry');
});

it('returns a values map with concrete strings', function () {
    $page = Page::factory()->create(['title' => 'Hello', 'meta_description' => '', 'published_at' => null]);
    $values = $this->tokens->values($page);

    expect($values)->toHaveKeys(['title', 'date', 'excerpt', 'author', 'starts_at', 'location']);
    expect($values['title'])->toBe('Hello');
    expect($values['date'])->toBe('');
    expect($values['excerpt'])->toBe('');
});
