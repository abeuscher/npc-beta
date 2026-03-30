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
