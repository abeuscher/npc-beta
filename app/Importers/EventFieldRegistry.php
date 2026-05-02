<?php

namespace App\Importers;

use App\Importers\Concerns\DerivesFromFillable;
use App\Importers\Concerns\FieldRegistry;
use App\Models\Event;

/**
 * Importable Event fields, derived from Event::$fillable. Returns keys as raw
 * Event column names (NOT prefixed) — prefixing to `event:*` happens in
 * EventImportFieldRegistry where fields share namespace with Registration /
 * Transaction / Contact-match buckets.
 */
class EventFieldRegistry extends FieldRegistry
{
    use DerivesFromFillable;

    protected static string $modelClass = Event::class;

    /**
     * Fields in Event::$fillable that must never appear in the importer dropdown.
     * System-managed, derived, or require special handling outside the column map.
     */
    protected static array $excluded = [
        'author_id',
        'sponsor_organization_id',
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
        'source',
        'import_source_id',
        'import_session_id',
    ];

    /**
     * Semantic type overrides. Keys are raw column names.
     */
    protected static array $typeOverrides = [
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'price'       => 'decimal',
        'capacity'    => 'integer',
        'description' => 'textarea',
    ];
}
