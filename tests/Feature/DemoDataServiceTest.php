<?php

use App\Models\Collection;
use App\Models\WidgetType;
use App\Services\DemoDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new DemoDataService();
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\WidgetTypeSeeder']);
});

// ── Field type generation ────────────────────────────────────────────────────

it('generates correct types for each field type', function () {
    $service = $this->service;

    expect($service->generateFieldValue('richtext'))->toBeString()->toContain('<p>');
    expect($service->generateFieldValue('richtext', 'article'))->toBeString()->toContain('<h2>');
    expect($service->generateFieldValue('text'))->toBeString()->not->toBeEmpty();
    expect($service->generateFieldValue('text', 'title'))->toBeString()->not->toBeEmpty();
    expect($service->generateFieldValue('text', 'email'))->toBe('jane.doe@example.org');
    expect($service->generateFieldValue('text', 'url'))->toBe('https://example.com/page');
    expect($service->generateFieldValue('text', 'phone'))->toBe('(555) 867-5309');
    expect($service->generateFieldValue('text', 'state'))->toMatch('/^[A-Z]{2}$/');
    expect($service->generateFieldValue('text', 'city'))->toBeString()->not->toBeEmpty();
    expect($service->generateFieldValue('text', 'address'))->toBeString()->not->toBeEmpty();
    expect($service->generateFieldValue('text', 'person_name'))->toBeString()->not->toBeEmpty();
    expect($service->generateFieldValue('textarea'))->toBeString()->not->toBeEmpty();
    expect($service->generateFieldValue('number'))->toBeInt();
    expect($service->generateFieldValue('number', 'currency'))->toBeFloat();
    expect($service->generateFieldValue('color'))->toMatch('/^#[0-9a-f]{6}$/');
    expect($service->generateFieldValue('toggle'))->toBeTrue();
    expect($service->generateFieldValue('image'))->toBeString()->toStartWith('https://loremflickr.com/');
    expect($service->generateFieldValue('video'))->toBeNull();
    expect($service->generateFieldValue('url'))->toBe('https://example.com');
    expect($service->generateFieldValue('buttons'))->toBeArray()->toHaveCount(2);
    expect($service->generateFieldValue('buttons')[0])->toHaveKeys(['text', 'url', 'style']);
});

it('generates select from options', function () {
    $value = $this->service->generateFieldValue('select', null, [
        'options' => ['a' => 'Option A', 'b' => 'Option B'],
    ]);
    expect($value)->toBe('a');
});

it('generates select from default', function () {
    $value = $this->service->generateFieldValue('select', null, [
        'default' => 'foo',
        'options' => ['a' => 'Option A'],
    ]);
    expect($value)->toBe('foo');
});

it('generates checkboxes from default', function () {
    $value = $this->service->generateFieldValue('checkboxes', null, [
        'default' => ['x', 'y'],
        'options' => ['x' => 'X', 'y' => 'Y', 'z' => 'Z'],
    ]);
    expect($value)->toBe(['x', 'y']);
});

// ── Widget-level generation ──────────────────────────────────────────────────

it('generates config for the hero widget with correct keys', function () {
    $hero = WidgetType::where('handle', 'hero')->firstOrFail();
    $config = $this->service->generateForWidget($hero);

    $expectedKeys = collect($hero->config_schema)
        ->pluck('key')
        ->filter()
        ->all();

    foreach ($expectedKeys as $key) {
        expect($config)->toHaveKey($key);
    }

    expect($config['content'])->toBeString()->toContain('<p>');
    expect($config['ctas'])->toBeArray();
    expect($config['fullscreen'])->toBeTrue();
    expect($config['background_image'])->toBeString()->toStartWith('https://loremflickr.com/');
});

it('generates config for the text_block widget', function () {
    $textBlock = WidgetType::where('handle', 'text_block')->firstOrFail();
    $config = $this->service->generateForWidget($textBlock);

    expect($config)->toHaveKey('content');
    expect($config['content'])->toBeString()->toContain('<p>');
});

it('generates config for the donation_form widget', function () {
    $donationForm = WidgetType::where('handle', 'donation_form')->firstOrFail();
    $config = $this->service->generateForWidget($donationForm);

    expect($config)->toHaveKeys(['heading', 'amounts', 'show_monthly', 'show_annual', 'success_page']);
    expect($config['heading'])->toBeString()->not->toBeEmpty();
});

it('generates config for every widget type without errors', function () {
    $widgetTypes = WidgetType::all();

    foreach ($widgetTypes as $wt) {
        $config = $this->service->generateForWidget($wt);
        expect($config)->toBeArray();

        // Every field with a key should be present
        $expectedKeys = collect($wt->config_schema)
            ->pluck('key')
            ->filter()
            ->all();

        foreach ($expectedKeys as $key) {
            expect(array_key_exists($key, $config))
                ->toBeTrue("Missing key '{$key}' for widget '{$wt->handle}'");
        }
    }
});

// ── Collection data generation ───────────────────────────────────────────────

it('generates event-shaped collection data', function () {
    $items = $this->service->generateCollectionData('events', 3);

    expect($items)->toHaveCount(3);
    expect($items[0])->toHaveKeys(['id', 'title', 'slug', 'starts_at', 'ends_at', 'is_virtual', 'is_free', 'url', 'thumbnail_url']);
    expect($items[0]['thumbnail_url'])->toBeString()->toStartWith('https://loremflickr.com/');
});

it('generates blog-post-shaped collection data', function () {
    $items = $this->service->generateCollectionData('blog_posts', 4);

    expect($items)->toHaveCount(4);
    expect($items[0])->toHaveKeys(['id', 'title', 'slug', 'published_at', 'thumbnail_url']);
});

it('generates product-shaped collection data', function () {
    $items = $this->service->generateCollectionData('products', 2);

    expect($items)->toHaveCount(2);
    expect($items[0])->toHaveKeys(['id', 'name', 'slug', 'description', 'capacity', 'available', 'image_url', 'prices']);
    expect($items[0]['prices'])->toBeArray()->not->toBeEmpty();
    expect($items[0]['prices'][0])->toHaveKeys(['id', 'label', 'amount', 'stripe_price_id']);
});

it('generates custom collection data from field schema', function () {
    $collection = Collection::create([
        'name'        => 'Test Collection',
        'handle'      => 'test-collection',
        'source_type' => 'custom',
        'fields'      => [
            ['key' => 'title', 'type' => 'text'],
            ['key' => 'description', 'type' => 'textarea'],
            ['key' => 'photo', 'type' => 'image'],
        ],
    ]);

    $items = $this->service->generateCollectionData('custom', 3, $collection);

    expect($items)->toHaveCount(3);
    expect($items[0])->toHaveKeys(['title', 'description', 'photo']);
    expect($items[0]['title'])->toBeString()->not->toBeEmpty();
    expect($items[0]['description'])->toBeString()->not->toBeEmpty();
    expect($items[0]['photo'])->toBeString()->toStartWith('https://loremflickr.com/');
});

it('returns empty array for custom collection without a collection instance', function () {
    $items = $this->service->generateCollectionData('custom', 3);

    expect($items)->toBe([]);
});

// ── Config schema group/subtype backfill ─────────────────────────────────────

it('has group key on all config schema fields after seeding', function () {
    $widgetTypes = WidgetType::all();

    foreach ($widgetTypes as $wt) {
        foreach ($wt->config_schema as $field) {
            if (! isset($field['key'])) {
                continue; // skip notice-type fields
            }
            expect(array_key_exists('group', $field))
                ->toBeTrue("Field '{$field['key']}' on '{$wt->handle}' missing group");
            expect($field['group'])->toBeIn(['content', 'appearance']);
        }
    }
});

it('has subtype on heading fields', function () {
    $widgetTypes = WidgetType::whereIn('handle', ['hero', 'events_listing', 'donation_form', 'board_members'])->get();

    foreach ($widgetTypes as $wt) {
        $headingField = collect($wt->config_schema)->firstWhere('key', 'heading');
        if ($headingField) {
            expect($headingField['subtype'] ?? null)->toBe('title', "Heading on '{$wt->handle}' should have subtype 'title'");
        }
    }
});

// ── Service does not persist ─────────────────────────────────────────────────

it('does not persist any data', function () {
    $hero = WidgetType::where('handle', 'hero')->firstOrFail();

    // Count rows in key tables before
    $pagesBefore = \App\Models\Page::count();
    $eventsBefore = \App\Models\Event::count();

    $this->service->generateForWidget($hero);
    $this->service->generateCollectionData('events', 5);
    $this->service->generateCollectionData('blog_posts', 5);
    $this->service->generateCollectionData('products', 5);

    expect(\App\Models\Page::count())->toBe($pagesBefore);
    expect(\App\Models\Event::count())->toBe($eventsBefore);
});
