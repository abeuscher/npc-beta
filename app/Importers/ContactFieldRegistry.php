<?php

namespace App\Importers;

use App\Models\Contact;

class ContactFieldRegistry
{
    /**
     * Fields in Contact::$fillable that must never appear in the importer dropdown.
     * These are system-managed or require special handling outside the column map.
     */
    private static array $excluded = [
        'organization_id',
        'household_id',
        'custom_data',
        'custom_fields',
        'source',
        'import_source_id',
        'import_session_id',
    ];

    /**
     * Semantic type overrides for fields whose type differs from plain 'text'.
     * type values: text, email, phone, boolean, external_id
     */
    private static array $typeOverrides = [
        'email'               => 'email',
        'email_secondary'     => 'email',
        'phone'               => 'phone',
        'phone_secondary'     => 'phone',
        'is_deceased'         => 'boolean',
        'do_not_contact'      => 'boolean',
        'mailing_list_opt_in' => 'boolean',
    ];

    /**
     * All importable fields, derived dynamically from Contact::$fillable.
     * Returns field_key => ['label' => string, 'type' => string].
     */
    public static function fields(): array
    {
        $excluded = static::$excluded;

        $fields = collect((new Contact())->getFillable())
            ->reject(fn ($field) => in_array($field, $excluded, true))
            ->mapWithKeys(fn ($field) => [
                $field => [
                    'label' => str($field)->replace('_', ' ')->title()->toString(),
                    'type'  => static::$typeOverrides[$field] ?? 'text',
                ],
            ])
            ->toArray();

        // external_id is not a real column — always appended last
        $fields['external_id'] = ['label' => 'External ID', 'type' => 'external_id'];

        return $fields;
    }

    /**
     * Returns field key → label pairs for use in Select dropdowns.
     */
    public static function options(): array
    {
        return array_map(fn ($def) => $def['label'], static::fields());
    }

    public static function typeOf(string $field): ?string
    {
        return static::fields()[$field]['type'] ?? null;
    }
}
