<?php

namespace App\Importers;

use App\Importers\Concerns\FieldRegistry;

/**
 * Importable Transaction fields for the events importer. `external_id` is the
 * universal payment dedupe key (Session 189 "Transaction ID"). Keys are raw
 * column names; EventImportFieldRegistry namespaces them to `transaction:*`.
 */
class TransactionFieldRegistry extends FieldRegistry
{
    public static function fields(): array
    {
        return [
            'external_id'     => ['label' => 'Transaction ID (external)', 'type' => 'text'],
            'amount'          => ['label' => 'Transaction Amount', 'type' => 'decimal'],
            'payment_state'   => ['label' => 'Payment State', 'type' => 'text'],
            'payment_method'  => ['label' => 'Payment Method', 'type' => 'text'],
            'payment_channel'  => ['label' => 'Payment Channel (online/offline)', 'type' => 'text'],
            'occurred_at'      => ['label' => 'Paid At', 'type' => 'datetime'],
            'invoice_number'   => ['label' => 'Invoice / Receipt Number', 'type' => 'text'],
        ];
    }
}
