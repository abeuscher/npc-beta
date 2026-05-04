<?php

namespace App\Services\Import;

class InvoiceFieldMapper
{
    public function map(string $sourceColumn, ?string $preset = null): ?string
    {
        $normalized = strtolower(trim($sourceColumn));
        $map = static::presetMap($preset ?? 'generic');

        return $map[$normalized] ?? null;
    }

    public static function presets(): array
    {
        return ['generic', 'wild_apricot', 'bloomerang'];
    }

    public static function presetMap(string $preset): array
    {
        return match ($preset) {
            'bloomerang'   => static::bloomerangMap(),
            'wild_apricot' => static::wildApricotMap(),
            default        => static::genericMap(),
        };
    }

    private static function genericMap(): array
    {
        return [
            'contact external id'                    => 'contact:external_id',
            'contact_external_id'                    => 'contact:external_id',
            'user id'                                => 'contact:external_id',
            'user_id'                                => 'contact:external_id',
            'userid'                                 => 'contact:external_id',
            'contact id'                             => 'contact:external_id',
            'contact_id'                             => 'contact:external_id',
            'contactid'                              => 'contact:external_id',
            'customer id'                            => 'contact:external_id',
            'customer_id'                            => 'contact:external_id',
            'customerid'                             => 'contact:external_id',

            'contact email'                          => 'contact:email',
            'contact_email'                          => 'contact:email',
            'contactemail'                           => 'contact:email',
            'email'                                  => 'contact:email',
            'email address'                          => 'contact:email',
            'email_address'                          => 'contact:email',
            'emailaddress'                           => 'contact:email',
            'e-mail'                                 => 'contact:email',

            'contact phone'                          => 'contact:phone',
            'contact_phone'                          => 'contact:phone',
            'contactphone'                           => 'contact:phone',
            'phone'                                  => 'contact:phone',
            'phone number'                           => 'contact:phone',
            'phone_number'                           => 'contact:phone',
            'phonenumber'                            => 'contact:phone',

            'invoice #'                              => 'invoice:invoice_number',
            'invoice number'                         => 'invoice:invoice_number',
            'invoice_number'                         => 'invoice:invoice_number',
            'invoicenumber'                          => 'invoice:invoice_number',
            'invoice id'                             => 'invoice:invoice_number',
            'invoice_id'                             => 'invoice:invoice_number',
            'invoiceid'                              => 'invoice:invoice_number',
            'invoice'                                => 'invoice:invoice_number',
            'order number'                           => 'invoice:invoice_number',
            'order_number'                           => 'invoice:invoice_number',
            'ordernumber'                            => 'invoice:invoice_number',

            'invoice date'                           => 'invoice:invoice_date',
            'invoice_date'                           => 'invoice:invoice_date',
            'invoicedate'                            => 'invoice:invoice_date',
            'date'                                   => 'invoice:invoice_date',
            'order date'                             => 'invoice:invoice_date',
            'order_date'                             => 'invoice:invoice_date',
            'orderdate'                              => 'invoice:invoice_date',

            'origin'                                 => 'invoice:origin',
            'invoice origin'                         => 'invoice:origin',
            'invoice_origin'                         => 'invoice:origin',
            'origin details'                         => 'invoice:origin_details',
            'origin_details'                         => 'invoice:origin_details',
            'origindetails'                          => 'invoice:origin_details',

            'ticket type (only for event invoices)'  => 'invoice:ticket_type',
            'ticket type'                            => 'invoice:ticket_type',
            'ticket_type'                            => 'invoice:ticket_type',
            'tickettype'                             => 'invoice:ticket_type',

            'status'                                 => 'invoice:status',
            'invoice status'                         => 'invoice:status',
            'invoice_status'                         => 'invoice:status',
            'invoicestatus'                          => 'invoice:status',
            'online/offline'                         => 'invoice:status',
            'payment state'                          => 'invoice:status',
            'payment_state'                          => 'invoice:status',
            'paymentstate'                           => 'invoice:status',

            'currency'                               => 'invoice:currency',
            'currency code'                          => 'invoice:currency',
            'currency_code'                          => 'invoice:currency',
            'currencycode'                           => 'invoice:currency',

            'payment date'                           => 'invoice:payment_date',
            'payment_date'                           => 'invoice:payment_date',
            'paymentdate'                            => 'invoice:payment_date',
            'paid date'                              => 'invoice:payment_date',
            'paid_date'                              => 'invoice:payment_date',
            'paiddate'                               => 'invoice:payment_date',
            'date paid'                              => 'invoice:payment_date',

            'settled payment type(s)'                => 'invoice:payment_type',
            'payment type'                           => 'invoice:payment_type',
            'payment_type'                           => 'invoice:payment_type',
            'paymenttype'                            => 'invoice:payment_type',
            'payment method'                         => 'invoice:payment_type',
            'payment_method'                         => 'invoice:payment_type',
            'paymentmethod'                          => 'invoice:payment_type',

            'item'                                   => 'invoice:item',
            'item name'                              => 'invoice:item',
            'item_name'                              => 'invoice:item',
            'itemname'                               => 'invoice:item',
            'item description'                       => 'invoice:item',
            'item_description'                       => 'invoice:item',
            'itemdescription'                        => 'invoice:item',
            'invoice item'                           => 'invoice:item',
            'invoice_item'                           => 'invoice:item',
            'description'                            => 'invoice:item',
            'line item'                              => 'invoice:item',
            'line_item'                              => 'invoice:item',
            'lineitem'                               => 'invoice:item',

            'item quantity'                          => 'invoice:item_quantity',
            'item_quantity'                          => 'invoice:item_quantity',
            'itemquantity'                           => 'invoice:item_quantity',
            'quantity'                               => 'invoice:item_quantity',
            'qty'                                    => 'invoice:item_quantity',

            'item price'                             => 'invoice:item_price',
            'item_price'                             => 'invoice:item_price',
            'itemprice'                              => 'invoice:item_price',
            'price'                                  => 'invoice:item_price',
            'unit price'                             => 'invoice:item_price',
            'unit_price'                             => 'invoice:item_price',
            'unitprice'                              => 'invoice:item_price',

            'item amount'                            => 'invoice:item_amount',
            'item_amount'                            => 'invoice:item_amount',
            'itemamount'                             => 'invoice:item_amount',
            'amount'                                 => 'invoice:item_amount',
            'line total'                             => 'invoice:item_amount',
            'line_total'                             => 'invoice:item_amount',
            'linetotal'                              => 'invoice:item_amount',

            'internal notes'                         => 'invoice:internal_notes',
            'internal_notes'                         => 'invoice:internal_notes',
            'internalnotes'                          => 'invoice:internal_notes',
            'notes'                                  => 'invoice:internal_notes',
            'admin notes'                            => 'invoice:internal_notes',
            'admin_notes'                            => 'invoice:internal_notes',
            'adminnotes'                             => 'invoice:internal_notes',
        ];
    }

    private static function wildApricotMap(): array
    {
        return [
            'user id'                                => 'contact:external_id',
            'email'                                  => 'contact:email',
            'email address'                          => 'contact:email',
            'phone'                                  => 'contact:phone',
            'phone number'                           => 'contact:phone',

            'invoice #'                              => 'invoice:invoice_number',
            'invoice number'                         => 'invoice:invoice_number',
            'invoice date'                           => 'invoice:invoice_date',
            'origin'                                 => 'invoice:origin',
            'origin details'                         => 'invoice:origin_details',
            'ticket type (only for event invoices)'  => 'invoice:ticket_type',
            'status'                                 => 'invoice:status',
            'currency'                               => 'invoice:currency',
            'payment date'                           => 'invoice:payment_date',
            'settled payment type(s)'                => 'invoice:payment_type',
            'item'                                   => 'invoice:item',
            'item quantity'                          => 'invoice:item_quantity',
            'item price'                             => 'invoice:item_price',
            'item amount'                            => 'invoice:item_amount',
            'internal notes'                         => 'invoice:internal_notes',

            'online/offline'                         => 'invoice:status',
        ];
    }

    private static function bloomerangMap(): array
    {
        return [
            'user id'                                => 'contact:external_id',
            'email'                                  => 'contact:email',
            'email address'                          => 'contact:email',
            'phone'                                  => 'contact:phone',
            'phone number'                           => 'contact:phone',

            'invoice #'                              => 'invoice:invoice_number',
            'invoice number'                         => 'invoice:invoice_number',
            'invoice date'                           => 'invoice:invoice_date',
            'origin'                                 => 'invoice:origin',
            'origin details'                         => 'invoice:origin_details',
            'ticket type (only for event invoices)'  => 'invoice:ticket_type',
            'status'                                 => 'invoice:status',
            'currency'                               => 'invoice:currency',
            'payment date'                           => 'invoice:payment_date',
            'settled payment type(s)'                => 'invoice:payment_type',
            'item'                                   => 'invoice:item',
            'item quantity'                          => 'invoice:item_quantity',
            'item price'                             => 'invoice:item_price',
            'item amount'                            => 'invoice:item_amount',
            'internal notes'                         => 'invoice:internal_notes',

            'online/offline'                         => 'invoice:status',
        ];
    }
}
