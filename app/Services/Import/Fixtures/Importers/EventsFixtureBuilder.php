<?php

namespace App\Services\Import\Fixtures\Importers;

use App\Services\Import\Fixtures\FixtureBuilder;
use Faker\Generator as Faker;

class EventsFixtureBuilder extends FixtureBuilder
{
    public function importer(): string
    {
        return 'events';
    }

    public function supportedPresets(): array
    {
        return ['generic'];
    }

    public function customFieldSentinel(): ?string
    {
        return '__custom_event__';
    }

    public function columnMap(string $preset): array
    {
        return [
            'Event Title'                            => 'event:title',
            'Event Slug'                             => 'event:slug',
            'Event Description'                      => 'event:description',
            'Event Starts At'                        => 'event:starts_at',
            'Event Ends At'                          => 'event:ends_at',
            'Event Status'                           => 'event:status',
            'Event Location'                         => 'event:location',
            'Event Price'                            => 'event:price',
            'Event Capacity'                         => 'event:capacity',
            'Event External ID'                      => 'event:external_id',
            'Registration Ticket Type'               => 'registration:ticket_type',
            'Registration Ticket Fee'                => 'registration:ticket_fee',
            'Registration Status'                    => 'registration:status',
            'Registration Payment State (snapshot)'  => 'registration:payment_state',
            'Registration Registered At'             => 'registration:registered_at',
            'Registration Notes'                     => 'registration:notes',
            'Contact Email'                          => 'contact:email',
            'Contact External ID'                    => 'contact:external_id',
            'Contact Phone'                          => 'contact:phone',
            'Transaction ID (external)'              => 'transaction:external_id',
            'Transaction Amount'                     => 'transaction:amount',
            'Payment State'                          => 'transaction:payment_state',
            'Payment Method'                         => 'transaction:payment_method',
            'Payment Channel (online/offline)'       => 'transaction:payment_channel',
            'Paid At'                                => 'transaction:occurred_at',
        ];
    }

    public function headers(string $preset): array
    {
        $headers = [
            'Event Title',
            'Event Slug',
            'Event Description',
            'Event Starts At',
            'Event Ends At',
            'Event Status',
            'Event Location',
            'Event Price',
            'Event Capacity',
            'Event External ID',
            'Registration Ticket Type',
            'Registration Ticket Fee',
            'Registration Status',
            'Registration Payment State (snapshot)',
            'Registration Registered At',
            'Registration Notes',
            'Contact Email',
            'Contact External ID',
            'Contact Phone',
            'Transaction ID (external)',
            'Transaction Amount',
            'Payment State',
            'Payment Method',
            'Payment Channel (online/offline)',
            'Paid At',
        ];

        foreach ($this->customFieldColumns($preset) as $cf) {
            $headers[] = $cf['header'];
        }

        return $headers;
    }

    public function customFieldColumns(string $preset): array
    {
        return [
            ['header' => 'Event Theme',   'handle' => 'event_theme',     'type' => 'text'],
            ['header' => 'Dietary Pref',  'handle' => 'dietary_pref',    'type' => 'text'],
        ];
    }

    public function cleanRow(int $rowIndex, string $preset, Faker $faker): array
    {
        $first  = $faker->firstName();
        $last   = $faker->lastName();
        $starts = $faker->dateTimeBetween('-1 year', '+6 months')->format('Y-m-d H:i:s');
        $ends   = date('Y-m-d H:i:s', strtotime($starts . ' +3 hours'));
        $title  = ucfirst($faker->words(3, true)) . ' ' . $rowIndex;

        return [
            'Event Title'                            => $title,
            'Event Slug'                             => '',
            'Event Description'                      => $faker->sentence(),
            'Event Starts At'                        => $starts,
            'Event Ends At'                          => $ends,
            'Event Status'                           => $faker->randomElement(['draft', 'published']),
            'Event Location'                         => $faker->city() . ' Convention Center',
            'Event Price'                            => (string) $faker->numberBetween(0, 250),
            'Event Capacity'                         => (string) $faker->numberBetween(10, 500),
            'Event External ID'                      => 'EVT-FIX-' . $rowIndex,
            'Registration Ticket Type'               => $faker->randomElement(['General', 'VIP', 'Student']),
            'Registration Ticket Fee'                => (string) $faker->numberBetween(0, 250),
            'Registration Status'                    => 'registered',
            'Registration Payment State (snapshot)'  => 'completed',
            'Registration Registered At'             => $starts,
            'Registration Notes'                     => $faker->sentence(),
            'Contact Email'                          => strtolower("{$first}.{$last}+{$rowIndex}@example.org"),
            'Contact External ID'                    => '',
            'Contact Phone'                          => $faker->phoneNumber(),
            'Transaction ID (external)'              => 'TXN-EVT-' . $rowIndex,
            'Transaction Amount'                     => (string) $faker->numberBetween(0, 250),
            'Payment State'                          => 'completed',
            'Payment Method'                         => $faker->randomElement(['credit_card', 'check']),
            'Payment Channel (online/offline)'       => 'online',
            'Paid At'                                => $starts,
            'Event Theme'                            => $faker->randomElement(['Innovation', 'Community', 'Outreach']),
            'Dietary Pref'                           => $faker->randomElement(['none', 'vegetarian', 'gluten-free']),
        ];
    }
}
