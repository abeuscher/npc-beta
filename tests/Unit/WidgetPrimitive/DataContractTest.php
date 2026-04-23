<?php

use App\WidgetPrimitive\ContentType;
use App\WidgetPrimitive\DataContract;

it('constructs with version and field list', function () {
    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_PAGE_CONTEXT,
        fields: ['title', 'date'],
    );

    expect($contract->version)->toBe('1.0.0')
        ->and($contract->source)->toBe('page_context')
        ->and($contract->fields)->toBe(['title', 'date'])
        ->and($contract->filters)->toBe([])
        ->and($contract->model)->toBeNull()
        ->and($contract->resourceHandle)->toBeNull()
        ->and($contract->contentType)->toBeNull();
});

it('carries filters, model, resource handle and content type for richer contracts', function () {
    $contentType = new ContentType(
        handle: 'carousel.slide',
        fields: [
            ['key' => 'title', 'type' => 'text'],
            ['key' => 'image', 'type' => 'image'],
        ],
    );

    $contract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_WIDGET_CONTENT_TYPE,
        fields: ['title'],
        filters: ['limit' => 5],
        resourceHandle: 'slides',
        contentType: $contentType,
    );

    expect($contract->filters)->toBe(['limit' => 5])
        ->and($contract->resourceHandle)->toBe('slides')
        ->and($contract->contentType)->toBe($contentType);
});

it('exposes image field keys from a content type', function () {
    $contentType = new ContentType(
        handle: 'carousel.slide',
        fields: [
            ['key' => 'title', 'type' => 'text'],
            ['key' => 'image', 'type' => 'image'],
            ['key' => 'thumbnail', 'type' => 'image'],
        ],
    );

    expect($contentType->imageFieldKeys())->toBe(['image', 'thumbnail'])
        ->and($contentType->fieldKeys())->toBe(['title', 'image', 'thumbnail']);
});
