<?php

namespace App\Importers;

use App\Importers\Concerns\DerivesFromFillable;
use App\Importers\Concerns\FieldRegistry;
use App\Models\Contact;

class ContactFieldRegistry extends FieldRegistry
{
    use DerivesFromFillable;

    protected static string $modelClass = Contact::class;

    /**
     * Fields in Contact::$fillable that must never appear in the importer dropdown.
     * These are system-managed or require special handling outside the column map.
     */
    protected static array $excluded = [
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
     */
    protected static array $typeOverrides = [
        'email'               => 'email',
        'email_secondary'     => 'email',
        'phone'               => 'phone',
        'phone_secondary'     => 'phone',
        'is_deceased'         => 'boolean',
        'do_not_contact'      => 'boolean',
        'mailing_list_opt_in' => 'boolean',
    ];
}
