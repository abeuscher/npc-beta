<?php

namespace App\WidgetPrimitive;

final class QuerySettings
{
    /**
     * @param  array<string, string>  $orderByOptions  value => label map of allowed order_by columns
     */
    public function __construct(
        public readonly bool $hasPanel,
        public readonly array $orderByOptions,
        public readonly bool $supportsTags,
    ) {}

    /**
     * Build the SOURCE_WIDGET_CONTENT_TYPE order_by allowlist: user-mapped
     * Collection text fields (the contract's content-type text fields) plus
     * the three CollectionItem system columns. Image fields are excluded.
     *
     * @param  array<int, string>  $textFieldKeys
     * @return array<string, string>
     */
    public static function swctOrderByOptions(array $textFieldKeys): array
    {
        $options = [];
        foreach ($textFieldKeys as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            $options[$key] = ucwords(str_replace(['_', '-'], ' ', $key));
        }
        $options['sort_order'] = 'Sort order';
        $options['created_at'] = 'Created';
        $options['updated_at'] = 'Updated';
        return $options;
    }
}
