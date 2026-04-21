<?php

namespace App\Importers;

use App\Importers\Concerns\AggregatingRegistry;

/**
 * Aggregates the Event / Registration / Contact-match / Transaction field
 * registries into a single namespaced dropdown for the events importer. Every
 * exposed key is prefixed with its bucket (`event:*`, `registration:*`,
 * `contact:*`, `transaction:*`) so the column_map stored on the ImportLog is
 * self-describing and the row processor can route a value to the right entity
 * without consulting the registry again.
 *
 * Contact match exposes only the subset of Contact fields usable as a contact
 * lookup key: this session never creates or updates contacts from an events
 * CSV.
 */
class EventImportFieldRegistry extends AggregatingRegistry
{
    protected static function buckets(): array
    {
        return [
            [
                'namespace'         => 'event',
                'registry'          => EventFieldRegistry::class,
                'group_label'       => 'Event fields',
                'flat_label_prefix' => 'Event',
            ],
            [
                'namespace'         => 'registration',
                'registry'          => RegistrationFieldRegistry::class,
                'group_label'       => 'Registration fields',
                'flat_label_prefix' => 'Registration',
            ],
            [
                'namespace'         => 'contact',
                'registry'          => ContactFieldRegistry::class,
                'group_label'       => 'Contact match',
                'flat_label_prefix' => 'Contact',
                'fields_subset'     => self::CONTACT_MATCH_KEYS,
            ],
            [
                'namespace'         => 'transaction',
                'registry'          => TransactionFieldRegistry::class,
                'group_label'       => 'Transaction fields',
                'flat_label_prefix' => 'Transaction',
            ],
        ];
    }

    protected static function otherOptions(): array
    {
        return [
            '__custom_event__'        => '— Create as Event custom field —',
            '__custom_registration__' => '— Create as Registration custom field —',
            '__tag_contact__'         => '— Apply as Contact tag —',
            '__tag_event__'           => '— Apply as Event tag —',
            '__note_contact__'        => '— Create as Contact note —',
            '__org_contact__'         => '— Link to Contact Organization —',
        ];
    }

    /**
     * Field keys that default to Event External ID (the required events match).
     * Used by the mapping step to derive a default "Match events by" value.
     */
    public static function defaultEventMatchKey(): string
    {
        return 'event:external_id';
    }
}
