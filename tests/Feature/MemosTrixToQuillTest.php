<?php

use App\Forms\Components\QuillEditor;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\WidgetPrimitive\Source;
use Filament\Forms\Components\RichEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->collection = Collection::create([
        'name'             => 'Memos',
        'handle'           => 'memos',
        'description'      => '',
        'source_type'      => 'custom',
        'is_public'        => false,
        'is_active'        => true,
        'accepted_sources' => [Source::HUMAN],
        'fields'           => [
            ['key' => 'title', 'label' => 'Title', 'type' => 'text',      'required' => true,  'helpText' => '', 'options' => []],
            ['key' => 'body',  'label' => 'Body',  'type' => 'rich_text', 'required' => true,  'helpText' => '', 'options' => []],
        ],
    ]);
});

it('renders rich_text fields via QuillEditor (not Trix RichEditor)', function () {
    $schema = $this->collection->getFormSchema();

    $bodyComponent = collect($schema)->first(
        fn ($c) => $c->getName() === 'data.body'
    );

    expect($bodyComponent)->toBeInstanceOf(QuillEditor::class)
        ->and($bodyComponent)->not->toBeInstanceOf(RichEditor::class);
});

it('sanitises rich_text data on CollectionItem save', function () {
    $item = CollectionItem::create([
        'collection_id' => $this->collection->id,
        'data'          => [
            'title' => 'Welcome',
            'body'  => '<p>Hello.</p><script>alert(1)</script><p onclick="x()">y</p>',
        ],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);

    expect($item->fresh()->data['body'])->toBe('<p>Hello.</p><p>y</p>');
    expect($item->fresh()->data['title'])->toBe('Welcome');
});

it('sanitises rich_text data on CollectionItem update', function () {
    $item = CollectionItem::create([
        'collection_id' => $this->collection->id,
        'data'          => ['title' => 'T', 'body' => '<p>clean</p>'],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);

    $item->update(['data' => ['title' => 'T', 'body' => '<p>edited</p><iframe></iframe>']]);

    expect($item->fresh()->data['body'])->toBe('<p>edited</p>');
});

it('migration converts trix-shape body to quill-shape and sanitises', function () {
    // Insert raw trix-shape data through the DB layer to bypass the saving hook
    // (mimics legacy / direct-DB-write payloads the migration is meant to clean up).
    $item = new CollectionItem([
        'collection_id' => $this->collection->id,
        'data'          => [
            'title' => 'Old Memo',
            'body'  => '<div>Hello world</div><div><strong>bold</strong></div><div>after</div>',
        ],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);
    $item->saveQuietly();

    $migration = require database_path('migrations/2026_05_10_120000_convert_collection_items_trix_to_quill.php');
    $migration->up();

    $stored = $item->fresh()->data['body'];
    expect($stored)->toBe('<p>Hello world</p><p><strong>bold</strong></p><p>after</p>');
});

it('migration is idempotent — second run is a no-op against quill-shape data', function () {
    $item = CollectionItem::create([
        'collection_id' => $this->collection->id,
        'data'          => ['title' => 'T', 'body' => '<p>quill shape</p>'],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);

    $migration = require database_path('migrations/2026_05_10_120000_convert_collection_items_trix_to_quill.php');
    $migration->up();
    $migration->up();

    expect($item->fresh()->data['body'])->toBe('<p>quill shape</p>');
});

it('migration leaves text fields untouched', function () {
    $item = new CollectionItem([
        'collection_id' => $this->collection->id,
        'data'          => [
            'title' => 'Plain <text> with brackets & ampersand',
            'body'  => '<div>x</div>',
        ],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);
    $item->saveQuietly();

    $migration = require database_path('migrations/2026_05_10_120000_convert_collection_items_trix_to_quill.php');
    $migration->up();

    expect($item->fresh()->data['title'])->toBe('Plain <text> with brackets & ampersand');
});

it('migration handles collections with no rich_text fields cleanly', function () {
    $textOnly = Collection::create([
        'name'             => 'Plain List',
        'handle'           => 'plain-list',
        'source_type'      => 'custom',
        'is_public'        => false,
        'is_active'        => true,
        'accepted_sources' => [Source::HUMAN],
        'fields'           => [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'helpText' => '', 'options' => []],
        ],
    ]);

    CollectionItem::create([
        'collection_id' => $textOnly->id,
        'data'          => ['name' => 'foo'],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);

    $migration = require database_path('migrations/2026_05_10_120000_convert_collection_items_trix_to_quill.php');

    expect(fn () => $migration->up())->not->toThrow(\Throwable::class);
});
