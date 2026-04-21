<?php

namespace App\Importers;

use App\Importers\Concerns\AggregatingRegistry;

/**
 * Aggregates Donation / Transaction / Contact-match field registries into a
 * single namespaced dropdown for the donations importer.
 */
class DonationImportFieldRegistry extends AggregatingRegistry
{
    protected static function buckets(): array
    {
        return [
            [
                'namespace'         => 'donation',
                'registry'          => DonationFieldRegistry::class,
                'group_label'       => 'Donation fields',
                'flat_label_prefix' => 'Donation',
            ],
            [
                'namespace'         => 'transaction',
                'registry'          => TransactionFieldRegistry::class,
                'group_label'       => 'Transaction fields',
                'flat_label_prefix' => 'Transaction',
            ],
            [
                'namespace'         => 'contact',
                'registry'          => ContactFieldRegistry::class,
                'group_label'       => 'Contact match',
                'flat_label_prefix' => 'Contact',
                'fields_subset'     => self::CONTACT_MATCH_KEYS,
            ],
        ];
    }

    protected static function otherOptions(): array
    {
        return [
            '__custom_donation__' => '— Create as Donation custom field —',
            '__tag_contact__'     => '— Apply as Contact tag —',
            '__note_contact__'    => '— Create as Contact note —',
            '__org_contact__'     => '— Link to Contact Organization —',
        ];
    }
}
