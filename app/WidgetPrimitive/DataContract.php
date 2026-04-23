<?php

namespace App\WidgetPrimitive;

final class DataContract
{
    public const SOURCE_PAGE_CONTEXT = 'page_context';
    public const SOURCE_SYSTEM_MODEL = 'system_model';
    public const SOURCE_WIDGET_CONTENT_TYPE = 'widget_content_type';

    /**
     * @param  string  $version  Contract version. Every contract carries one from day one.
     * @param  string  $source  One of the SOURCE_* constants.
     * @param  array<int, string>  $fields  Field names the widget declares it consumes. Fail-closed: anything not declared is not populated. Omit or pass an empty array for SOURCE_PAGE_CONTEXT contracts — the source itself is the capability boundary (see PageContextTokens::TOKENS).
     * @param  array<string, mixed>  $filters  Query-shape options (limit, order_by, etc.).
     * @param  string|null  $model  For SOURCE_SYSTEM_MODEL: which model ('post' for now).
     * @param  string|null  $resourceHandle  For SOURCE_WIDGET_CONTENT_TYPE: the collection handle to read items from.
     * @param  ContentType|null  $contentType  For SOURCE_WIDGET_CONTENT_TYPE: the widget-declared content shape.
     */
    public function __construct(
        public readonly string $version,
        public readonly string $source,
        public readonly array $fields = [],
        public readonly array $filters = [],
        public readonly ?string $model = null,
        public readonly ?string $resourceHandle = null,
        public readonly ?ContentType $contentType = null,
    ) {}
}
