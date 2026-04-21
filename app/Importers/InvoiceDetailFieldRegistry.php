<?php

namespace App\Importers;

use App\Importers\Concerns\FieldRegistry;

/**
 * Importable Invoice Detail fields. Keys are raw column names; the aggregate
 * InvoiceImportFieldRegistry namespaces them to `invoice:*`.
 */
class InvoiceDetailFieldRegistry extends FieldRegistry
{
    public static function fields(): array
    {
        return [
            'invoice_number'  => ['label' => 'Invoice #', 'type' => 'text'],
            'invoice_date'    => ['label' => 'Invoice Date', 'type' => 'datetime'],
            'origin'          => ['label' => 'Origin', 'type' => 'text'],
            'origin_details'  => ['label' => 'Origin Details', 'type' => 'text'],
            'ticket_type'     => ['label' => 'Ticket Type', 'type' => 'text'],
            'status'          => ['label' => 'Invoice Status', 'type' => 'text'],
            'currency'        => ['label' => 'Currency', 'type' => 'text'],
            'payment_date'    => ['label' => 'Payment Date', 'type' => 'datetime'],
            'payment_type'    => ['label' => 'Payment Type', 'type' => 'text'],
            'item'            => ['label' => 'Item Description', 'type' => 'text'],
            'item_quantity'   => ['label' => 'Item Quantity', 'type' => 'decimal'],
            'item_price'      => ['label' => 'Item Price', 'type' => 'decimal'],
            'item_amount'     => ['label' => 'Item Amount', 'type' => 'decimal'],
            'internal_notes'  => ['label' => 'Internal Notes', 'type' => 'textarea'],
        ];
    }
}
