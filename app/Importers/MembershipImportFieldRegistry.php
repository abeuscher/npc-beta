<?php

namespace App\Importers;

use App\Importers\Concerns\AggregatingRegistry;

/**
 * Aggregates Membership / Contact-match field registries into a single
 * namespaced dropdown for the memberships importer.
 */
class MembershipImportFieldRegistry extends AggregatingRegistry
{
    protected static function buckets(): array
    {
        return [
            [
                'namespace'         => 'membership',
                'registry'          => MembershipFieldRegistry::class,
                'group_label'       => 'Membership fields',
                'flat_label_prefix' => 'Membership',
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
            '__custom_membership__' => '— Create as Membership custom field —',
            '__tag_contact__'       => '— Apply as Contact tag —',
            '__note_contact__'      => '— Create as Contact note —',
            '__org_contact__'       => '— Link to Contact Organization —',
            '__org_member__'        => '— Link to Organization (Member) —',
        ];
    }
}
