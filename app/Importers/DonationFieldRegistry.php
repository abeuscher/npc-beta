<?php

namespace App\Importers;

use App\Importers\Concerns\FieldRegistry;

/**
 * Importable Donation fields. Keys are raw column names; the aggregate
 * DonationImportFieldRegistry namespaces them to `donation:*`.
 */
class DonationFieldRegistry extends FieldRegistry
{
    public static function fields(): array
    {
        return [
            'amount'         => ['label' => 'Donation Amount', 'type' => 'decimal'],
            'donated_at'     => ['label' => 'Donation Date', 'type' => 'datetime'],
            'type'           => ['label' => 'Type (one_off / recurring)', 'type' => 'text'],
            'status'         => ['label' => 'Status', 'type' => 'text'],
            'external_id'    => ['label' => 'External ID', 'type' => 'external_id'],
            'invoice_number' => ['label' => 'Invoice / Receipt Number', 'type' => 'text'],
            'comment'        => ['label' => 'Comment / Notes', 'type' => 'textarea'],
        ];
    }
}
