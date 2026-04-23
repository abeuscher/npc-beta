<?php

namespace App\WidgetPrimitive;

final class ContentType
{
    /**
     * @param  array<int, array{key: string, type: string, label?: string}>  $fields
     */
    public function __construct(
        public readonly string $handle,
        public readonly array $fields,
    ) {}

    public function fieldKeys(): array
    {
        return array_values(array_filter(array_map(fn ($f) => $f['key'] ?? null, $this->fields)));
    }

    public function imageFieldKeys(): array
    {
        return array_values(array_filter(array_map(
            fn ($f) => ($f['type'] ?? null) === 'image' ? ($f['key'] ?? null) : null,
            $this->fields
        )));
    }
}
