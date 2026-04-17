<?php

namespace App\Importers;

/**
 * Importable Transaction fields for the events importer. `external_id` is the
 * universal payment dedupe key (Session 189 "Transaction ID"). Keys are raw
 * column names; EventImportFieldRegistry namespaces them to `transaction:*`.
 */
class TransactionFieldRegistry
{
    public static function fields(): array
    {
        return [
            'external_id'     => ['label' => 'Transaction ID (external)', 'type' => 'text'],
            'amount'          => ['label' => 'Amount', 'type' => 'decimal'],
            'payment_state'   => ['label' => 'Payment State', 'type' => 'text'],
            'payment_method'  => ['label' => 'Payment Method', 'type' => 'text'],
            'payment_channel' => ['label' => 'Payment Channel (online/offline)', 'type' => 'text'],
            'occurred_at'     => ['label' => 'Paid At', 'type' => 'datetime'],
        ];
    }

    public static function options(): array
    {
        return array_map(fn ($def) => $def['label'], static::fields());
    }
}
