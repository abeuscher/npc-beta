<?php

namespace App\WidgetPrimitive;

final class DataContract
{
    public const SOURCE_PAGE_CONTEXT = 'page_context';
    public const SOURCE_RECORD_CONTEXT = 'record_context';
    public const SOURCE_SYSTEM_MODEL = 'system_model';
    public const SOURCE_WIDGET_CONTENT_TYPE = 'widget_content_type';

    public const CARDINALITY_MANY = 'many';
    public const CARDINALITY_ONE = 'one';

    /**
     * @param  string  $version  Contract version. Every contract carries one from day one.
     * @param  string  $source  One of the SOURCE_* constants.
     * @param  array<int, string>  $fields  Field names the widget declares it consumes. Fail-closed: anything not declared is not populated. Omit or pass an empty array for SOURCE_PAGE_CONTEXT contracts — the source itself is the capability boundary (see PageContextTokens::TOKENS).
     * @param  array<string, mixed>  $filters  Query-shape options (limit, order_by, etc.). Single-row contracts carry their unique-key lookup here (e.g. ['slug' => $slug]).
     * @param  string|null  $model  For SOURCE_SYSTEM_MODEL: which model ('post' for now).
     * @param  string|null  $resourceHandle  For SOURCE_WIDGET_CONTENT_TYPE: the collection handle to read items from.
     * @param  ContentType|null  $contentType  For SOURCE_WIDGET_CONTENT_TYPE: the widget-declared content shape.
     * @param  QuerySettings|null  $querySettings  Honored-knob declaration. List-shaped contracts carry one; PAGE_CONTEXT and single-row contracts pass null.
     * @param  string  $cardinality  CARDINALITY_MANY (default) returns ['items' => [...]]; CARDINALITY_ONE returns ['item' => row | null].
     * @param  string|null  $requiredPermission  Spatie permission name the authenticated user must hold for ContractResolver::resolve() to dispatch this contract. Null means no permission gate (public-by-construction). When set and denied, the resolver returns the cardinality-appropriate empty shape without dispatching to the source arm.
     * @param  array<string, string>  $formatHints  Per-instance display-format overrides keyed by projector field name; values are DateFormat constants. Validated by the projector against each field's option-set helper; unknown values fall back to the per-field default.
     */
    public function __construct(
        public readonly string $version,
        public readonly string $source,
        public readonly array $fields = [],
        public readonly array $filters = [],
        public readonly ?string $model = null,
        public readonly ?string $resourceHandle = null,
        public readonly ?ContentType $contentType = null,
        public readonly ?QuerySettings $querySettings = null,
        public readonly string $cardinality = self::CARDINALITY_MANY,
        public readonly ?string $requiredPermission = null,
        public readonly array $formatHints = [],
    ) {}

    /**
     * Source-arm default for `order_by` when the user has not supplied one and
     * the contract's filters carry no `order_by`. Read by ContractResolver.
     */
    public function orderByDefault(): string
    {
        return match ($this->source) {
            self::SOURCE_WIDGET_CONTENT_TYPE => 'sort_order',
            self::SOURCE_SYSTEM_MODEL => match ($this->model) {
                'event'   => 'starts_at',
                'post'    => 'published_at',
                'product' => 'sort_order',
                default   => 'created_at',
            },
            default => 'created_at',
        };
    }
}
