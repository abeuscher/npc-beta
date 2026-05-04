<?php

namespace App\Services\Import\Fixtures\Importers;

use App\Services\Import\Fixtures\FixtureBuilder;
use Faker\Generator as Faker;

class InvoiceDetailsFixtureBuilder extends FixtureBuilder
{
    public function importer(): string
    {
        return 'invoice_details';
    }

    public function supportedPresets(): array
    {
        return ['generic'];
    }

    public function customFieldSentinel(): ?string
    {
        return '__custom_invoice__';
    }

    public function columnMap(string $preset): array
    {
        return [
            'Invoice #'        => 'invoice:invoice_number',
            'Invoice Date'     => 'invoice:invoice_date',
            'Origin'           => 'invoice:origin',
            'Origin Details'   => 'invoice:origin_details',
            'Ticket Type'      => 'invoice:ticket_type',
            'Invoice Status'   => 'invoice:status',
            'Currency'         => 'invoice:currency',
            'Payment Date'     => 'invoice:payment_date',
            'Payment Type'     => 'invoice:payment_type',
            'Item Description' => 'invoice:item',
            'Item Quantity'    => 'invoice:item_quantity',
            'Item Price'       => 'invoice:item_price',
            'Item Amount'      => 'invoice:item_amount',
            'Internal Notes'   => 'invoice:internal_notes',
            'Email'            => 'contact:email',
            'User ID'          => 'contact:external_id',
            'Phone'            => 'contact:phone',
        ];
    }

    public function headers(string $preset): array
    {
        $headers = [
            'Invoice #',
            'Invoice Date',
            'Origin',
            'Origin Details',
            'Ticket Type',
            'Invoice Status',
            'Currency',
            'Payment Date',
            'Payment Type',
            'Item Description',
            'Item Quantity',
            'Item Price',
            'Item Amount',
            'Internal Notes',
            'Email',
            'User ID',
            'Phone',
        ];

        foreach ($this->customFieldColumns($preset) as $cf) {
            $headers[] = $cf['header'];
        }

        return $headers;
    }

    public function customFieldColumns(string $preset): array
    {
        return [
            ['header' => 'PO Number',  'handle' => 'po_number',  'type' => 'text'],
            ['header' => 'Tax Exempt', 'handle' => 'tax_exempt', 'type' => 'boolean'],
        ];
    }

    public function cleanRow(int $rowIndex, string $preset, Faker $faker): array
    {
        $first   = $faker->firstName();
        $last    = $faker->lastName();
        $date    = $faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d');
        $qty     = $faker->numberBetween(1, 5);
        $price   = $faker->numberBetween(20, 200);
        $amount  = $qty * $price;

        return [
            'Invoice #'        => 'INV-FIX-' . $rowIndex,
            'Invoice Date'     => $date,
            'Origin'           => $faker->randomElement(['event', 'membership', 'product']),
            'Origin Details'   => $faker->sentence(3),
            'Ticket Type'      => $faker->randomElement(['General', 'VIP', '']),
            'Invoice Status'   => $faker->randomElement(['paid', 'pending', 'cancelled']),
            'Currency'         => 'USD',
            'Payment Date'     => $date,
            'Payment Type'     => $faker->randomElement(['credit_card', 'check', 'ach']),
            'Item Description' => $faker->words(3, true),
            'Item Quantity'    => (string) $qty,
            'Item Price'       => (string) $price,
            'Item Amount'      => (string) $amount,
            'Internal Notes'   => $faker->sentence(),
            'Email'            => strtolower("{$first}.{$last}+{$rowIndex}@example.org"),
            'User ID'          => '',
            'Phone'            => $faker->phoneNumber(),
            'PO Number'        => 'PO-' . $faker->numberBetween(1000, 9999),
            'Tax Exempt'       => $faker->boolean(20) ? 'Yes' : 'No',
        ];
    }
}
