<?php

namespace App\Importers;

use App\Importers\Concerns\DerivesFromFillable;
use App\Importers\Concerns\FieldRegistry;
use App\Models\Organization;

/**
 * Importable Organization fields, derived from Organization::$fillable. Returns
 * keys as raw Organization column names (NOT prefixed) — prefixing to
 * `organization:*` happens in OrganizationImportFieldRegistry.
 */
class OrganizationFieldRegistry extends FieldRegistry
{
    use DerivesFromFillable;

    protected static string $modelClass = Organization::class;

    /**
     * Fields in Organization::$fillable that must never appear in the importer
     * dropdown. System-managed columns owned by the source-policy / import-trace
     * machinery rather than mappable from CSV.
     */
    protected static array $excluded = [
        'source',
        'custom_fields',
        'import_source_id',
        'import_session_id',
    ];

    protected static array $typeOverrides = [];
}
