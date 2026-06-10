<?php

// Tier-1 chrome-in-preview (session A010 follow-on): the page builder's
// bootstrap payload carries read-only header/footer bands resolved exactly
// as the public layout resolves chrome (chromeSlot suppression/inheritance,
// ChromeRenderer output), plus an edit_url deep link into the template
// chrome editor for operators holding edit_site_chrome. The bands exist
// only for Page owners that receive chrome publicly — never for the chrome
// (system) pages themselves.

use App\Livewire\PageBuilder;
use App\Models\Page;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    User::factory()->create();
    $this->seed(\Database\Seeders\SystemPageSeeder::class);
});

function chromeBootstrapFor(Page $page): array
{
    return Livewire::test(PageBuilder::class, ['pageId' => $page->id])
        ->instance()
        ->getBootstrapData();
}

it('carries rendered header and footer bands for a default page', function () {
    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);

    $chrome = chromeBootstrapFor($page)['chrome'];

    expect($chrome['header'])->not->toBeNull()
        ->and($chrome['header']['html'])->not->toBe('')
        ->and($chrome['footer'])->not->toBeNull()
        // The seeded footer is the columns nav — proves real widget output.
        ->and($chrome['footer']['html'])->toContain('widget-nav--columns');
});

it('omits edit_url without the edit_site_chrome permission and includes it with it', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $template = Template::factory()->create(['type' => 'page', 'is_default' => true]);
    $page = Page::factory()->create(['type' => 'default', 'status' => 'published']);

    // Guest (no authenticated user): bands render, links do not.
    $chrome = chromeBootstrapFor($page)['chrome'];
    expect($chrome['header']['edit_url'])->toBeNull();

    $editor = User::factory()->create();
    $editor->givePermissionTo('edit_site_chrome');
    $this->actingAs($editor);

    $chrome = chromeBootstrapFor($page)['chrome'];
    expect($chrome['header']['edit_url'])->toContain((string) $template->id)
        ->and($chrome['header']['edit_url'])->toContain('page_template_header')
        ->and($chrome['footer']['edit_url'])->toContain('page_template_footer');
});

it('renders no bands when the page builder is editing a chrome page itself', function () {
    $headerPage = Page::where('slug', '_header')->where('type', 'system')->firstOrFail();

    $chrome = chromeBootstrapFor($headerPage)['chrome'];

    expect($chrome['header'])->toBeNull()
        ->and($chrome['footer'])->toBeNull();
});

it('honours per-template chrome suppression', function () {
    $template = Template::factory()->create([
        'type'       => 'page',
        'is_default' => true,
        'no_header'  => true,
    ]);
    $page = Page::factory()->create([
        'type'        => 'default',
        'status'      => 'published',
        'template_id' => $template->id,
    ]);

    $chrome = chromeBootstrapFor($page)['chrome'];

    expect($chrome['header'])->toBeNull()
        ->and($chrome['footer'])->not->toBeNull();
});
