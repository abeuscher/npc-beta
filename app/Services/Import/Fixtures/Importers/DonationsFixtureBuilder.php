<?php

namespace App\Services\Import\Fixtures\Importers;

use App\Services\Import\Fixtures\FixtureBuilder;
use Faker\Generator as Faker;

class DonationsFixtureBuilder extends FixtureBuilder
{
    public function importer(): string
    {
        return 'donations';
    }

    public function supportedPresets(): array
    {
        return ['generic'];
    }

    public function customFieldSentinel(): ?string
    {
        return '__custom_donation__';
    }

    public function columnMap(string $preset): array
    {
        return [
            'Donation Amount'                  => 'donation:amount',
            'Donation Date'                    => 'donation:donated_at',
            'Type (one_off / recurring)'       => 'donation:type',
            'Status'                           => 'donation:status',
            'External ID'                      => 'donation:external_id',
            'Invoice / Receipt Number'         => 'donation:invoice_number',
            'Comment / Notes'                  => 'donation:comment',
            'Transaction Amount'               => 'transaction:amount',
            'Payment State'                    => 'transaction:payment_state',
            'Payment Method'                   => 'transaction:payment_method',
            'Payment Channel (online/offline)' => 'transaction:payment_channel',
            'Paid At'                          => 'transaction:occurred_at',
            'Email'                            => 'contact:email',
            'User ID'                          => 'contact:external_id',
            'Phone'                            => 'contact:phone',
        ];
    }

    public function headers(string $preset): array
    {
        $headers = [
            'Donation Amount',
            'Donation Date',
            'Type (one_off / recurring)',
            'Status',
            'External ID',
            'Invoice / Receipt Number',
            'Comment / Notes',
            'Transaction Amount',
            'Payment State',
            'Payment Method',
            'Payment Channel (online/offline)',
            'Paid At',
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
            ['header' => 'Campaign',     'handle' => 'campaign',     'type' => 'text'],
            ['header' => 'Anonymous',    'handle' => 'anonymous',    'type' => 'boolean'],
            ['header' => 'Pledge Date',  'handle' => 'pledge_date',  'type' => 'date'],
        ];
    }

    public function cleanRow(int $rowIndex, string $preset, Faker $faker): array
    {
        $first  = $faker->firstName();
        $last   = $faker->lastName();
        $amount = $faker->numberBetween(10, 5000);
        $date   = $faker->dateTimeBetween('-3 years', 'now')->format('Y-m-d');

        return [
            'Donation Amount'                  => (string) $amount,
            'Donation Date'                    => $date,
            'Type (one_off / recurring)'       => $faker->randomElement(['one_off', 'recurring']),
            'Status'                           => 'active',
            'External ID'                      => 'DON-FIX-' . $rowIndex,
            'Invoice / Receipt Number'         => 'INV-FIX-' . $rowIndex,
            'Comment / Notes'                  => $faker->sentence(),
            'Transaction Amount'               => (string) $amount,
            'Payment State'                    => 'completed',
            'Payment Method'                   => $faker->randomElement(['credit_card', 'check', 'ach']),
            'Payment Channel (online/offline)' => $faker->randomElement(['online', 'offline']),
            'Paid At'                          => $date,
            'Email'                            => strtolower("{$first}.{$last}+{$rowIndex}@example.org"),
            'User ID'                          => '',
            'Phone'                            => $faker->phoneNumber(),
            'Campaign'                         => $faker->randomElement(['Annual Fund', 'Spring Campaign', 'Year End']),
            'Anonymous'                        => $faker->boolean(15) ? 'Yes' : 'No',
            'Pledge Date'                      => $faker->boolean(40) ? $date : '',
        ];
    }
}
