<?php

namespace App\Importers;

use App\Importers\Concerns\AggregatingRegistry;

/**
 * Single-bucket aggregator for the Organizations importer. Orgs are top-level
 * records — no contact-match bucket. The three `__*_organization__` sentinels
 * cover custom-field / tag / note destinations on the Org row.
 */
class OrganizationImportFieldRegistry extends AggregatingRegistry
{
    protected static function buckets(): array
    {
        return [
            [
                'namespace'         => 'organization',
                'registry'          => OrganizationFieldRegistry::class,
                'group_label'       => 'Organization fields',
                'flat_label_prefix' => 'Organization',
            ],
        ];
    }

    protected static function otherOptions(): array
    {
        return [
            '__custom_organization__' => '— Create as Organization custom field —',
            '__tag_organization__'    => '— Apply as Organization tag —',
            '__note_organization__'   => '— Create as Organization note —',
        ];
    }
}
