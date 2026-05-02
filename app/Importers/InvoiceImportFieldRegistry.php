<?php

namespace App\Importers;

use App\Importers\Concerns\AggregatingRegistry;

/**
 * Aggregates Invoice Detail / Contact-match field registries into a single
 * namespaced dropdown for the invoice details importer.
 */
class InvoiceImportFieldRegistry extends AggregatingRegistry
{
    protected static function buckets(): array
    {
        return [
            [
                'namespace'         => 'invoice',
                'registry'          => InvoiceDetailFieldRegistry::class,
                'group_label'       => 'Invoice fields',
                'flat_label_prefix' => 'Invoice',
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
            '__custom_invoice__'     => '— Create as Transaction custom field —',
            '__tag_contact__'        => '— Apply as Contact tag —',
            '__note_contact__'       => '— Create as Contact note —',
            '__org_contact__'        => '— Link to Contact Organization —',
            '__org_invoice_party__'  => '— Link to Organization (Bill-To) —',
        ];
    }
}
