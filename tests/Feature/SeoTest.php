<?php

use App\Models\Event;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use App\Rules\ValidHtmlSnippet;
use App\Services\SeoMetaGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── SEO meta output ──────────────────────────────────────────────────────────

it('renders title from meta_title when set', function () {
    Page::factory()->create([
        'slug'             => 'home',
        'title'            => 'Home',
        'meta_title'       => 'Custom SEO Title',
        'status'           => 'published',
        'published_at'     => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Custom SEO Title</title>', false);
});

it('falls back to page title when meta_title is empty', function () {
    Page::factory()->create([
        'slug'         => 'about',
        'title'        => 'About Our Org',
        'meta_title'   => null,
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/about')
        ->assertOk()
        ->assertSee('<title>About Our Org</title>', false);
});

it('renders meta description when set', function () {
    Page::factory()->create([
        'slug'             => 'home',
        'title'            => 'Home',
        'meta_description' => 'A great nonprofit.',
        'status'           => 'published',
        'published_at'     => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<meta name="description" content="A great nonprofit.">', false);
});

it('renders OG tags', function () {
    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'meta_title'   => 'OG Test',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<meta property="og:title" content="OG Test">', false)
        ->assertSee('<meta property="og:type" content="website">', false);
});

it('renders canonical URL', function () {
    SiteSetting::set('base_url', 'https://example.org');

    Page::factory()->create([
        'slug'         => 'about',
        'title'        => 'About',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/about')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://example.org/about">', false);
});

it('renders canonical URL as base_url for home page', function () {
    SiteSetting::set('base_url', 'https://example.org');

    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://example.org">', false);
});

it('falls back to the page title for an empty-string meta_title (not just null)', function () {
    // Concrete-values: meta_title is stored as '' when unset. The view must use
    // ?: (empty-aware), not ?? (null-only), or the title/og:title render empty.
    Page::factory()->create([
        'slug'         => 'mission',
        'title'        => 'Our Mission',
        'meta_title'   => '',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/mission')
        ->assertOk()
        ->assertSee('<title>Our Mission</title>', false)
        ->assertSee('<meta property="og:title" content="Our Mission">', false);
});

it('populates og:title from the page title across public page types', function () {
    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home Org',
        'status'       => 'published',
        'published_at' => now(),
    ]);
    Page::factory()->create([
        'slug'         => 'about',
        'title'        => 'About Page',
        'status'       => 'published',
        'published_at' => now(),
    ]);
    Page::factory()->create([
        'slug'         => 'news/hello-world',
        'title'        => 'Hello Post',
        'type'         => 'post',
        'status'       => 'published',
        'published_at' => now(),
    ]);
    Page::factory()->create([
        'slug'         => 'events/gala',
        'title'        => 'Gala Event',
        'type'         => 'event',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/')->assertSee('<meta property="og:title" content="Home Org">', false);
    $this->get('/about')->assertSee('<meta property="og:title" content="About Page">', false);
    $this->get('/news/hello-world')->assertSee('<meta property="og:title" content="Hello Post">', false);
    $this->get('/events/gala')->assertSee('<meta property="og:title" content="Gala Event">', false);
});

// ── Canonical homepage URL ───────────────────────────────────────────────────

it('301-redirects /home to / so the homepage has a single crawlable URL', function () {
    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/home')
        ->assertStatus(301)
        ->assertRedirect('/');
});

// ── Client-side __site privacy ───────────────────────────────────────────────

it('does not leak the contact email into the client-side __site script', function () {
    config(['site.contact_email' => 'private@nphelper.com']);

    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('window.__site', false)   // block still renders
        ->assertDontSee('contactEmail', false)
        ->assertDontSee('private@nphelper.com', false);
});

// ── Noindex ──────────────────────────────────────────────────────────────────

it('renders noindex meta tag when noindex is true', function () {
    Page::factory()->create([
        'slug'         => 'thanks',
        'title'        => 'Thank You',
        'noindex'      => true,
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/thanks')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex">', false);
});

it('does not render noindex meta tag when noindex is false', function () {
    Page::factory()->create([
        'slug'         => 'about',
        'title'        => 'About',
        'noindex'      => false,
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/about')
        ->assertOk()
        ->assertDontSee('noindex');
});

it('emits noindex,nofollow site-wide when the noindex_global setting is on', function () {
    SiteSetting::set('noindex_global', 'true');

    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'noindex'      => false,
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
});

it('omits the global noindex meta tag when the noindex_global setting is off', function () {
    SiteSetting::set('noindex_global', 'false');

    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'noindex'      => false,
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('noindex');
});

it('global noindex_global overrides per-page noindex with the more restrictive nofollow form', function () {
    SiteSetting::set('noindex_global', 'true');

    Page::factory()->create([
        'slug'         => 'thanks',
        'title'        => 'Thank You',
        'noindex'      => true,
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/thanks')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
        ->assertDontSee('content="noindex">', false);
});

// ── JSON-LD ──────────────────────────────────────────────────────────────────

it('generates BlogPosting JSON-LD for post pages', function () {
    $user = User::factory()->create(['name' => 'Jane Author']);

    $page = Page::factory()->create([
        'slug'         => 'news/my-post',
        'title'        => 'My Post',
        'type'         => 'post',
        'author_id'    => $user->id,
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $seo = SeoMetaGenerator::forPage($page);
    $ld  = json_decode($seo['json_ld'], true);

    expect($ld['@type'])->toBe('BlogPosting')
        ->and($ld['headline'])->toBe('My Post')
        ->and($ld['author']['name'])->toBe('Jane Author');
});

it('generates WebPage JSON-LD for default pages', function () {
    $page = Page::factory()->create([
        'slug'         => 'about',
        'title'        => 'About',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $seo = SeoMetaGenerator::forPage($page);
    $ld  = json_decode($seo['json_ld'], true);

    expect($ld['@type'])->toBe('WebPage')
        ->and($ld['name'])->toBe('About');
});

it('generates Event JSON-LD for event pages', function () {
    $page = Page::factory()->create([
        'slug'         => 'events/gala',
        'title'        => 'Annual Gala',
        'type'         => 'event',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    Event::factory()->create([
        'landing_page_id' => $page->id,
        'starts_at'       => '2026-06-15 18:00:00',
    ]);

    $seo = SeoMetaGenerator::forPage($page);
    $ld  = json_decode($seo['json_ld'], true);

    expect($ld['@type'])->toBe('Event')
        ->and($ld['name'])->toBe('Annual Gala')
        ->and($ld)->toHaveKey('startDate');
});

// ── Custom JSON-LD slot ──────────────────────────────────────────────────────

it('emits a site-wide custom JSON-LD graph as a separate ld+json script', function () {
    SiteSetting::set('custom_json_ld', '{"@context":"https://schema.org","@type":"Organization","name":"Test Org"}');

    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"name":"Test Org"', false);
});

it('emits a per-page custom JSON-LD graph from custom_fields', function () {
    Page::factory()->create([
        'slug'          => 'team/jane',
        'title'         => 'Jane',
        'status'        => 'published',
        'published_at'  => now(),
        'custom_fields' => ['json_ld' => '{"@context":"https://schema.org","@type":"Person","name":"Jane Doe"}'],
    ]);

    $this->get('/team/jane')
        ->assertOk()
        ->assertSee('"@type":"Person"', false)
        ->assertSee('"name":"Jane Doe"', false);
});

it('emits the auto graph plus both custom graphs as three separate ld+json scripts', function () {
    SiteSetting::set('custom_json_ld', '{"@type":"Organization","name":"Site Org"}');

    Page::factory()->create([
        'slug'          => 'team/jane',
        'title'         => 'Jane',
        'status'        => 'published',
        'published_at'  => now(),
        'custom_fields' => ['json_ld' => '{"@type":"Person","name":"Jane Doe"}'],
    ]);

    $content = $this->get('/team/jane')->assertOk()->getContent();

    // Auto WebPage graph + site-wide Organization + per-page Person.
    expect(substr_count($content, 'application/ld+json'))->toBe(3);
});

it('escapes a </script> breakout attempt in custom JSON-LD so it cannot break out', function () {
    $payload = '</script><script>alert(1)</script>';
    SiteSetting::set('custom_json_ld', '{"@type":"Organization","name":"' . $payload . '"}');

    $page = Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    // JSON_HEX_TAG escapes every < and > in the emitted string, so it carries
    // no raw angle brackets — the HTML parser can never see a closing </script>
    // — yet the value round-trips intact when decoded.
    $encoded = SeoMetaGenerator::forPage($page)['custom_json_ld'][0];
    expect($encoded)->not->toContain('<')
        ->and($encoded)->not->toContain('>')
        ->and(json_decode($encoded, true)['name'])->toBe($payload);

    // And the rendered page never carries the raw injected <script> tag.
    $this->get('/')
        ->assertOk()
        ->assertDontSee('<script>alert(1)', false);
});

it('drops invalid JSON in the custom slot instead of emitting it raw', function () {
    SiteSetting::set('custom_json_ld', '{ this is not valid json');

    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('not valid json', false);
});

it('drops a non-object/array scalar in the custom slot', function () {
    SiteSetting::set('custom_json_ld', '"just a bare string"');

    $page = Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    expect(SeoMetaGenerator::forPage($page)['custom_json_ld'])->toBe([]);
});

// ── ValidJsonLd rule ─────────────────────────────────────────────────────────

it('passes a JSON object through the JSON-LD rule', function () {
    $rule   = new \App\Rules\ValidJsonLd();
    $failed = false;

    $rule->validate('json_ld', '{"@type":"Organization"}', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('passes a JSON array through the JSON-LD rule', function () {
    $rule   = new \App\Rules\ValidJsonLd();
    $failed = false;

    $rule->validate('json_ld', '[{"@type":"Organization"},{"@type":"Person"}]', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('passes a blank value through the JSON-LD rule', function () {
    $rule   = new \App\Rules\ValidJsonLd();
    $failed = false;

    $rule->validate('json_ld', '', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('fails malformed JSON in the JSON-LD rule', function () {
    $rule   = new \App\Rules\ValidJsonLd();
    $failed = false;

    $rule->validate('json_ld', '{ not valid', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

it('fails a bare scalar in the JSON-LD rule', function () {
    $rule   = new \App\Rules\ValidJsonLd();
    $failed = false;

    $rule->validate('json_ld', '42', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

// ── Snippet validation ───────────────────────────────────────────────────────

it('passes valid HTML through the snippet rule', function () {
    $rule   = new ValidHtmlSnippet();
    $failed = false;

    $rule->validate('test', '<script>var x = 1;</script>', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('passes empty value through the snippet rule', function () {
    $rule   = new ValidHtmlSnippet();
    $failed = false;

    $rule->validate('test', '', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

// ── Snippet rendering ────────────────────────────────────────────────────────

it('renders per-page head snippet in the head', function () {
    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'head_snippet' => '<!-- test-head-snippet -->',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<!-- test-head-snippet -->', false);
});

it('renders per-page body snippet before closing body', function () {
    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'body_snippet' => '<!-- test-body-snippet -->',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<!-- test-body-snippet -->', false);
});

it('renders site-wide head snippet', function () {
    SiteSetting::set('site_head_snippet', '<!-- site-head -->');

    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<!-- site-head -->', false);
});

it('renders site-wide body-open snippet after body tag', function () {
    SiteSetting::set('site_body_open_snippet', '<!-- gtm-noscript -->');

    Page::factory()->create([
        'slug'         => 'home',
        'title'        => 'Home',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<!-- gtm-noscript -->', false);
});

// ── Sitemap ──────────────────────────────────────────────────────────────────

it('returns valid XML sitemap with published pages', function () {
    SiteSetting::set('base_url', 'https://example.org');

    Page::factory()->create([
        'slug'         => 'about',
        'title'        => 'About',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    Page::factory()->create([
        'slug'         => 'draft-page',
        'title'        => 'Draft',
        'status'       => 'draft',
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('https://example.org/about')
        ->assertDontSee('draft-page');
});

it('excludes system and member pages from sitemap', function () {
    SiteSetting::set('base_url', 'https://example.org');

    Page::factory()->create([
        'slug'         => 'system/login',
        'title'        => 'Login',
        'type'         => 'system',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    Page::factory()->create([
        'slug'         => 'members/dashboard',
        'title'        => 'Dashboard',
        'type'         => 'member',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    Page::factory()->create([
        'slug'         => 'contact',
        'title'        => 'Contact',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('https://example.org/contact')
        ->assertDontSee('system/login')
        ->assertDontSee('members/dashboard');
});

it('excludes noindex pages from sitemap', function () {
    SiteSetting::set('base_url', 'https://example.org');

    Page::factory()->create([
        'slug'         => 'thank-you',
        'title'        => 'Thanks',
        'noindex'      => true,
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertDontSee('thank-you');
});

// ── Robots.txt ───────────────────────────────────────────────────────────────

it('returns robots.txt with sitemap reference', function () {
    SiteSetting::set('base_url', 'https://example.org');

    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee('User-agent: *')
        ->assertSee('Sitemap: https://example.org/sitemap.xml');
});

// ── /llms.txt ────────────────────────────────────────────────────────────────

it('returns /llms.txt with site name, description, and a published page link', function () {
    SiteSetting::set('site_name', 'Example Org');
    SiteSetting::set('site_description', 'A great nonprofit.');
    SiteSetting::set('contact_email', 'hello@example.org');
    SiteSetting::set('base_url', 'https://example.org');

    Page::factory()->create([
        'slug'         => 'about',
        'title'        => 'About',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $this->get('/llms.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee('# Example Org', false)
        ->assertSee('> A great nonprofit.', false)
        ->assertSee('- [About](https://example.org/about)', false)
        ->assertSee('Email: hello@example.org', false);
});

// ── Permission gating ────────────────────────────────────────────────────────

it('hides snippet action from users without edit_page_snippets permission', function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);

    $user = User::factory()->create();
    $user->assignRole('cms_editor');

    $page = Page::factory()->create([
        'slug'   => 'about',
        'title'  => 'About',
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->get(route('filament.admin.resources.pages.edit', $page))
        ->assertOk()
        ->assertDontSee('Edit Header &amp; Footer Snippets');
});
