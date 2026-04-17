<?php

namespace App\Importers;

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
class EventImportFieldRegistry
{
    public const CONTACT_MATCH_KEYS = ['email', 'external_id', 'phone'];

    /**
     * Filament grouped-options shape. Keys are Optgroup labels; values are
     * option-key => label maps.
     */
    public static function groupedOptions(): array
    {
        $prefix = fn (string $ns, array $map) => collect($map)
            ->mapWithKeys(fn ($label, $key) => ["{$ns}:{$key}" => $label])
            ->all();

        $contactMatch = collect(ContactFieldRegistry::options())
            ->only(static::CONTACT_MATCH_KEYS)
            ->all();

        return [
            'Event fields'        => $prefix('event',       EventFieldRegistry::options()),
            'Registration fields' => $prefix('registration', RegistrationFieldRegistry::options()),
            'Contact match'       => $prefix('contact',     $contactMatch),
            'Transaction fields'  => $prefix('transaction', TransactionFieldRegistry::options()),
            'Other'               => [
                '__custom_event__'        => '— Create as Event custom field —',
                '__custom_registration__' => '— Create as Registration custom field —',
                '__tag_contact__'         => '— Apply as Contact tag —',
                '__tag_event__'           => '— Apply as Event tag —',
                '__note_contact__'        => '— Create as Contact note —',
                '__org_contact__'         => '— Link to Contact Organization —',
            ],
        ];
    }

    /**
     * Flat options list (prefixed-key => label), suitable for the "match by"
     * selects. Does not include relational destinations.
     */
    public static function flatFields(): array
    {
        $out = [];

        foreach (EventFieldRegistry::options() as $k => $label) {
            $out["event:{$k}"] = "Event — {$label}";
        }

        foreach (RegistrationFieldRegistry::options() as $k => $label) {
            $out["registration:{$k}"] = "Registration — {$label}";
        }

        foreach (TransactionFieldRegistry::options() as $k => $label) {
            $out["transaction:{$k}"] = "Transaction — {$label}";
        }

        foreach (static::CONTACT_MATCH_KEYS as $k) {
            $label = ContactFieldRegistry::fields()[$k]['label'] ?? ucfirst($k);
            $out["contact:{$k}"] = "Contact — {$label}";
        }

        return $out;
    }

    /**
     * Split a namespaced key into [namespace, field]. Returns [null, null] for
     * unrecognised inputs.
     */
    public static function split(string $key): array
    {
        if (! str_contains($key, ':')) {
            return [null, null];
        }

        [$ns, $field] = explode(':', $key, 2);

        if (! in_array($ns, ['event', 'registration', 'contact', 'transaction'], true)) {
            return [null, null];
        }

        return [$ns, $field];
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
