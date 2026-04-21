<?php

namespace App\Importers;

use App\Importers\Concerns\FieldRegistry;

/**
 * Importable Membership fields. Keys are raw column names; the aggregate
 * MembershipImportFieldRegistry namespaces them to `membership:*`.
 */
class MembershipFieldRegistry extends FieldRegistry
{
    public static function fields(): array
    {
        return [
            'tier'        => ['label' => 'Membership Level / Tier', 'type' => 'text'],
            'status'      => ['label' => 'Membership Status', 'type' => 'text'],
            'starts_on'   => ['label' => 'Member Since', 'type' => 'datetime'],
            'expires_on'  => ['label' => 'Renewal Due / Expires On', 'type' => 'datetime'],
            'amount_paid' => ['label' => 'Amount Paid', 'type' => 'decimal'],
            'notes'       => ['label' => 'Notes', 'type' => 'textarea'],
            'external_id' => ['label' => 'External ID', 'type' => 'external_id'],
        ];
    }
}
