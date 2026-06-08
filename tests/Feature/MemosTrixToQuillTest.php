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
