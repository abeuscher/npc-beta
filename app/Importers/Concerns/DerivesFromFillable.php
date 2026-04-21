<?php

namespace App\Importers\Concerns;

/**
 * Provides a `fields()` implementation derived from the concrete class's
 * declared `$modelClass`, `$excluded`, and `$typeOverrides`. Intended for
 * use alongside the FieldRegistry base on entities where the importable
 * columns mirror Model::$fillable (Contact, Event).
 *
 * Concrete classes must declare:
 *   - protected static string $modelClass
 *   - protected static array $excluded
 *   - protected static array $typeOverrides
 */
trait DerivesFromFillable
{
    public static function fields(): array
    {
        $model         = new static::$modelClass;
        $excluded      = static::$excluded ?? [];
        $typeOverrides = static::$typeOverrides ?? [];

        $fields = collect($model->getFillable())
            ->reject(fn ($field) => in_array($field, $excluded, true))
            ->mapWithKeys(fn ($field) => [
                $field => [
                    'label' => str($field)->replace('_', ' ')->title()->toString(),
                    'type'  => $typeOverrides[$field] ?? 'text',
                ],
            ])
            ->toArray();

        // external_id is not a real column — always appended last.
        $fields['external_id'] = ['label' => 'External ID', 'type' => 'external_id'];

        return $fields;
    }
}
