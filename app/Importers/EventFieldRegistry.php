<?php

namespace App\Importers;

use App\Models\Event;

/**
 * Importable Event fields, derived from Event::$fillable. Returns keys as raw
 * Event column names (NOT prefixed) — prefixing to `event:*` happens in
 * EventImportFieldRegistry where fields share namespace with Registration /
 * Transaction / Contact-match buckets.
 */
class EventFieldRegistry
{
    /**
     * Fields in Event::$fillable that must never appear in the importer dropdown.
     * System-managed, derived, or require special handling outside the column map.
     */
    private static array $excluded = [
        'author_id',
        'landing_page_id',
        'registrants_deleted_at',
        'custom_fields',
        'registration_mode',
        'auto_create_contacts',
        'mailing_list_opt_in_enabled',
        'external_registration_url',
        'map_url',
        'map_label',
        'meeting_url',
        'meeting_label',
        'meeting_details',
    ];

    /**
     * Semantic type overrides. Keys are raw column names.
     * Types: text, datetime, decimal, integer, boolean, textarea, external_id.
     */
    private static array $typeOverrides = [
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'price'       => 'decimal',
        'capacity'    => 'integer',
        'description' => 'textarea',
    ];

    public static function fields(): array
    {
        $excluded = static::$excluded;

        $fields = collect((new Event())->getFillable())
            ->reject(fn ($field) => in_array($field, $excluded, true))
            ->mapWithKeys(fn ($field) => [
                $field => [
                    'label' => str($field)->replace('_', ' ')->title()->toString(),
                    'type'  => static::$typeOverrides[$field] ?? 'text',
                ],
            ])
            ->toArray();

        $fields['external_id'] = ['label' => 'External ID', 'type' => 'external_id'];

        return $fields;
    }

    public static function options(): array
    {
        return array_map(fn ($def) => $def['label'], static::fields());
    }

    public static function typeOf(string $field): ?string
    {
        return static::fields()[$field]['type'] ?? null;
    }
}
