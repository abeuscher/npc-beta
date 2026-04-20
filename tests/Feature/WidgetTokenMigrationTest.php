<?php

use App\Models\Page;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function tokenMigrationFilePath(): string
{
    return database_path('migrations/2026_04_20_120000_rewrite_listing_widget_per_item_tokens.php');
}

function runTokenMigration(string $direction): void
{
    $migration = require tokenMigrationFilePath();
    $direction === 'up' ? $migration->up() : $migration->down();
}

function seedTokenTestPage(): Page
{
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    return Page::factory()->create([
        'title'        => 'Hosting Page',
        'slug'         => 'hosting',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);
}

function makeTokenTestWidget(Page $page, string $handle, string $field, string $template): string
{
    $wt = WidgetType::where('handle', $handle)->firstOrFail();

    $pw = $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [$field => $template],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    return $pw->id;
}

function readTokenTestTemplate(string $pwId, string $field): ?string
{
    $row = DB::table('page_widgets')->where('id', $pwId)->first(['config']);
    $cfg = json_decode($row->config ?? 'null', true) ?? [];
    return $cfg[$field] ?? null;
}

// ── Up: rewrites bare tokens to item-namespaced for in-scope widgets ────────

it('migration rewrites events_listing bare tokens to item-namespaced', function () {
    $page = seedTokenTestPage();
    $id = makeTokenTestWidget(
        $page,
        'events_listing',
        'content_template',
        '<h3>{{title}}</h3><p>{{date}} at {{location}}</p><p>{{slug}}</p>'
    );

    runTokenMigration('up');

    expect(readTokenTestTemplate($id, 'content_template'))
        ->toBe('<h3>{{item.title}}</h3><p>{{item.date}} at {{item.location}}</p><p>{{item.slug}}</p>');
});

it('migration rewrites blog_listing bare tokens to item-namespaced', function () {
    $page = seedTokenTestPage();
    $id = makeTokenTestWidget(
        $page,
        'blog_listing',
        'content_template',
        '<h3>{{title}}</h3><p>{{excerpt}}</p><p>{{date}}</p>'
    );

    runTokenMigration('up');

    expect(readTokenTestTemplate($id, 'content_template'))
        ->toBe('<h3>{{item.title}}</h3><p>{{item.excerpt}}</p><p>{{item.date}}</p>');
});

it('migration rewrites carousel narrow token set to item-namespaced', function () {
    $page = seedTokenTestPage();
    $id = makeTokenTestWidget($page, 'carousel', 'caption_template', '{{title}} — {{date}}');

    runTokenMigration('up');

    expect(readTokenTestTemplate($id, 'caption_template'))
        ->toBe('{{item.title}} — {{item.date}}');
});

// ── Idempotency: second up-run does not change anything ─────────────────────

it('migration is idempotent — second up run is a no-op', function () {
    $page = seedTokenTestPage();
    $id = makeTokenTestWidget(
        $page,
        'events_listing',
        'content_template',
        '<h3>{{title}}</h3>'
    );

    runTokenMigration('up');
    $after_first = readTokenTestTemplate($id, 'content_template');

    runTokenMigration('up');
    $after_second = readTokenTestTemplate($id, 'content_template');

    expect($after_first)->toBe('<h3>{{item.title}}</h3>')
        ->and($after_second)->toBe($after_first);
});

// ── Down: reverses the rewrite ──────────────────────────────────────────────

it('migration down restores bare tokens from item-namespaced', function () {
    $page = seedTokenTestPage();
    $id = makeTokenTestWidget(
        $page,
        'events_listing',
        'content_template',
        '<h3>{{title}}</h3><p>{{location}}</p>'
    );

    runTokenMigration('up');
    expect(readTokenTestTemplate($id, 'content_template'))
        ->toBe('<h3>{{item.title}}</h3><p>{{item.location}}</p>');

    runTokenMigration('down');
    expect(readTokenTestTemplate($id, 'content_template'))
        ->toBe('<h3>{{title}}</h3><p>{{location}}</p>');
});

// ── Scope: untouched widget types are not rewritten ─────────────────────────

it('migration leaves non-listing widgets untouched', function () {
    $page = seedTokenTestPage();
    $hero = WidgetType::where('handle', 'hero')->firstOrFail();

    $pw = $page->widgets()->create([
        'widget_type_id' => $hero->id,
        'config'         => ['content' => '<h1>{{title}}</h1>'],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    runTokenMigration('up');

    $row = DB::table('page_widgets')->where('id', $pw->id)->first(['config']);
    $cfg = json_decode($row->config, true);

    expect($cfg['content'])->toBe('<h1>{{title}}</h1>');
});

// ── Scope: carousel tokens outside the narrow set are NOT rewritten ─────────

it('migration does not rewrite carousel tokens outside the narrow set', function () {
    $page = seedTokenTestPage();
    // 'slug' is a typical collection-item key but is NOT in the narrow set
    // for carousel (which is PageContextTokens::TOKENS only).
    $id = makeTokenTestWidget($page, 'carousel', 'caption_template', '{{title}} ({{slug}})');

    runTokenMigration('up');

    expect(readTokenTestTemplate($id, 'caption_template'))
        ->toBe('{{item.title}} ({{slug}})');
});
