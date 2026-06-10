<?php

use App\Models\HelpArticle;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Gating ───────────────────────────────────────────────────────────────────
// PUBLIC_WEBSITE defaults to false; every docs surface must be a 404 on every
// non-marketing instance — byte-identical in behavior to before the feature.

it('404s every docs surface when public_website is off (the default)', function () {
    $article = HelpArticle::factory()->create();

    expect(config('site.public_website'))->toBeFalse();

    $this->get('/docs')->assertNotFound();
    $this->get("/docs/{$article->slug}")->assertNotFound();
    $this->get("/docs/{$article->slug}.md")->assertNotFound();
    $this->get('/llms-full.txt')->assertNotFound();
});

it('serves every docs surface when public_website is on', function () {
    config(['site.public_website' => true]);

    $article = HelpArticle::factory()->create();

    $this->get('/docs')->assertOk();
    $this->get("/docs/{$article->slug}")->assertOk();
    $this->get("/docs/{$article->slug}.md")->assertOk();
    $this->get('/llms-full.txt')->assertOk();
});

it('404s an unknown article slug even with the flag on', function () {
    config(['site.public_website' => true]);

    $this->get('/docs/no-such-article')->assertNotFound();
    $this->get('/docs/no-such-article.md')->assertNotFound();
});

// ── Docs index ───────────────────────────────────────────────────────────────

it('lists articles grouped by category on the docs index', function () {
    config(['site.public_website' => true]);

    HelpArticle::factory()->create(['title' => 'Managing Contacts', 'category' => 'crm']);
    HelpArticle::factory()->create(['title' => 'Theme Reference', 'category' => 'cms']);

    $this->get('/docs')
        ->assertOk()
        ->assertSeeInOrder(['CRM', 'Managing Contacts', 'CMS', 'Theme Reference']);
});

// ── HTML article shape ───────────────────────────────────────────────────────

it('renders the extraction-structured article page', function () {
    config(['site.public_website' => true]);
    SiteSetting::set('site_name', 'Example Org');

    $article = HelpArticle::factory()->create([
        'title'        => 'Managing Contacts',
        'slug'         => 'managing-contacts',
        'description'  => 'How contacts work.',
        'content'      => "# Managing Contacts\n\nContacts are the core record.",
        'last_updated' => '2026-04-01',
    ]);

    $this->get('/docs/managing-contacts')
        ->assertOk()
        ->assertSee('<title>Managing Contacts — Example Org Docs</title>', false)
        ->assertSee('<h1>Managing Contacts</h1>', false)
        ->assertSee('How contacts work.')
        ->assertSee('Last updated April 1, 2026')
        ->assertSee('Contacts are the core record.')
        ->assertSee('/docs/managing-contacts.md');
});

it('renders only one H1 even though the body opens with its own', function () {
    config(['site.public_website' => true]);

    $article = HelpArticle::factory()->create([
        'title'   => 'Solo Heading',
        'content' => "# Solo Heading\n\nBody text here.",
    ]);

    $html = $this->get("/docs/{$article->slug}")->assertOk()->getContent();

    expect(substr_count($html, '<h1>'))->toBe(1);
});

it('renders the breadcrumb chain for an article with a parent', function () {
    config(['site.public_website' => true]);

    HelpArticle::factory()->create([
        'title' => 'Widgets Overview',
        'slug'  => 'widgets-overview',
    ]);

    HelpArticle::factory()->create([
        'title'       => 'Bar Chart Widget',
        'slug'        => 'bar-chart-widget',
        'parent_slug' => 'widgets-overview',
    ]);

    $this->get('/docs/bar-chart-widget')
        ->assertOk()
        ->assertSeeInOrder(['Docs', 'Widgets Overview', 'Bar Chart Widget']);
});

it('links related articles sharing a tag', function () {
    config(['site.public_website' => true]);

    $article = HelpArticle::factory()->create(['tags' => ['payments']]);
    $related = HelpArticle::factory()->create(['title' => 'Stripe Setup Guide', 'tags' => ['payments']]);

    $this->get("/docs/{$article->slug}")
        ->assertOk()
        ->assertSee('Stripe Setup Guide')
        ->assertSee("/docs/{$related->slug}");
});

// ── Markdown sibling ─────────────────────────────────────────────────────────

it('serves the raw markdown sibling with the canonical header block', function () {
    config(['site.public_website' => true]);
    SiteSetting::set('base_url', 'https://example.org');

    HelpArticle::factory()->create([
        'title'        => 'Managing Contacts',
        'slug'         => 'managing-contacts',
        'description'  => 'How contacts work.',
        'content'      => "# Managing Contacts\n\n## Adding a Contact\n\n```php\nContact::create();\n```",
        'last_updated' => '2026-04-01',
    ]);

    $response = $this->get('/docs/managing-contacts.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8');

    $body = $response->getContent();

    expect($body)->toContain('# Managing Contacts')
        ->toContain('How contacts work.')
        ->toContain('Last updated: 2026-04-01')
        ->toContain('Canonical: https://example.org/docs/managing-contacts')
        ->toContain('## Adding a Contact')   // markdown survives unrendered
        ->toContain("```php");
});

// ── llms.txt ─────────────────────────────────────────────────────────────────

it('appends a Documentation section to llms.txt when the flag is on', function () {
    config(['site.public_website' => true]);
    SiteSetting::set('base_url', 'https://example.org');

    HelpArticle::factory()->create([
        'title'       => 'Managing Contacts',
        'slug'        => 'managing-contacts',
        'description' => 'How contacts work.',
    ]);

    $this->get('/llms.txt')
        ->assertOk()
        ->assertSee('## Documentation', false)
        ->assertSee('- [Managing Contacts](https://example.org/docs/managing-contacts.md) — How contacts work.', false);
});

it('omits the Documentation section from llms.txt when the flag is off', function () {
    SiteSetting::set('site_name', 'Example Org');
    SiteSetting::set('base_url', 'https://example.org');

    HelpArticle::factory()->create();

    Page::factory()->create([
        'slug'         => 'about',
        'title'        => 'About',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/llms.txt')
        ->assertOk()
        ->assertSee('- [About](https://example.org/about)', false)
        ->assertDontSee('## Documentation');
});

// ── sitemap.xml ──────────────────────────────────────────────────────────────

it('adds docs URLs with lastmod to the sitemap when the flag is on', function () {
    config(['site.public_website' => true]);
    SiteSetting::set('base_url', 'https://example.org');

    HelpArticle::factory()->create([
        'slug'         => 'managing-contacts',
        'last_updated' => '2026-04-01',
    ]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<loc>https://example.org/docs</loc>', false)
        ->assertSee('<loc>https://example.org/docs/managing-contacts</loc>', false)
        ->assertSee('2026-04-01', false);
});

it('falls back to updated_at for sitemap lastmod when last_updated is null', function () {
    config(['site.public_website' => true]);
    SiteSetting::set('base_url', 'https://example.org');

    $article = HelpArticle::factory()->create([
        'slug'         => 'no-date-article',
        'last_updated' => null,
    ]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<loc>https://example.org/docs/no-date-article</loc>', false)
        ->assertSee($article->updated_at->toW3cString(), false);
});

it('keeps docs URLs out of the sitemap when the flag is off', function () {
    SiteSetting::set('base_url', 'https://example.org');

    HelpArticle::factory()->create(['slug' => 'managing-contacts']);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertDontSee('/docs');
});

// ── llms-full.txt ────────────────────────────────────────────────────────────

it('serves the concatenated corpus at llms-full.txt when the flag is on', function () {
    config(['site.public_website' => true]);
    SiteSetting::set('site_name', 'Example Org');
    SiteSetting::set('base_url', 'https://example.org');

    HelpArticle::factory()->create([
        'title'   => 'Managing Contacts',
        'slug'    => 'managing-contacts',
        'content' => "# Managing Contacts\n\nContacts body.",
    ]);
    HelpArticle::factory()->create([
        'title'   => 'Theme Reference',
        'slug'    => 'theme-reference',
        'content' => "# Theme Reference\n\nTheme body.",
    ]);

    $response = $this->get('/llms-full.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    $body = $response->getContent();

    expect($body)->toContain('# Example Org')
        ->toContain('# Managing Contacts')
        ->toContain('Canonical: https://example.org/docs/managing-contacts')
        ->toContain('Contacts body.')
        ->toContain('# Theme Reference')
        ->toContain('Theme body.')
        ->toContain("---");
});

// ── robots.txt stays permissive ──────────────────────────────────────────────

it('keeps robots.txt permissive on a docs-enabled instance', function () {
    config(['site.public_website' => true]);
    SiteSetting::set('base_url', 'https://example.org');

    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('User-agent: *')
        ->assertSee('Allow: /')
        ->assertSee('Sitemap: https://example.org/sitemap.xml')
        ->assertDontSee('Disallow');
});

// ── Catchall non-regression ──────────────────────────────────────────────────

it('still serves a published page at its slug alongside the docs routes', function () {
    config(['site.public_website' => true]);

    Page::factory()->create([
        'slug'         => 'about',
        'title'        => 'About Our Org',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/about')
        ->assertOk()
        ->assertSee('About Our Org');
});

it('a page cannot shadow the docs index — the named route wins', function () {
    config(['site.public_website' => true]);

    Page::factory()->create([
        'slug'         => 'docs',
        'title'        => 'Impostor Docs Page',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    HelpArticle::factory()->create(['title' => 'Real Docs Article']);

    $this->get('/docs')
        ->assertOk()
        ->assertSee('Real Docs Article')
        ->assertDontSee('Impostor Docs Page');
});
