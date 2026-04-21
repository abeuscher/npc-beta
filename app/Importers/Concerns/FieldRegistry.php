<?php

namespace App\Importers\Concerns;

/**
 * Base for single-entity importable-field registries. Concrete classes declare
 * `fields()` (either hand-curated or via the DerivesFromFillable trait); this
 * base provides the boilerplate `options()` and `typeOf()` derived from it.
 */
abstract class FieldRegistry
{
    /**
     * Returns field_key => ['label' => string, 'type' => string].
     */
    abstract public static function fields(): array;

    public static function options(): array
    {
        return array_map(fn ($def) => $def['label'], static::fields());
    }

    public static function typeOf(string $field): ?string
    {
        return static::fields()[$field]['type'] ?? null;
    }
}
