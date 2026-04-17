<?php

namespace App\Importers;

/**
 * Importable EventRegistration fields. Hand-curated — the fillable list on
 * EventRegistration includes many columns populated by the public registration
 * flow (stripe_payment_intent_id, address_*, etc) that don't belong in a CSV
 * importer. Keys are raw column names; EventImportFieldRegistry namespaces them
 * to `registration:*`.
 */
class RegistrationFieldRegistry
{
    public static function fields(): array
    {
        return [
            'ticket_type'    => ['label' => 'Ticket Type', 'type' => 'text'],
            'ticket_fee'     => ['label' => 'Ticket Fee', 'type' => 'decimal'],
            'status'         => ['label' => 'Status', 'type' => 'text'],
            'payment_state'  => ['label' => 'Payment State (snapshot)', 'type' => 'text'],
            'registered_at'  => ['label' => 'Registered At', 'type' => 'datetime'],
            'notes'          => ['label' => 'Registration Notes', 'type' => 'textarea'],
        ];
    }

    public static function options(): array
    {
        return array_map(fn ($def) => $def['label'], static::fields());
    }
}
