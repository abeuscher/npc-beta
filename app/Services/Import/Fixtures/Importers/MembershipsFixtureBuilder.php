<?php

namespace App\Services\Import\Fixtures\Importers;

use App\Services\Import\Fixtures\FixtureBuilder;
use Faker\Generator as Faker;

class MembershipsFixtureBuilder extends FixtureBuilder
{
    public function importer(): string
    {
        return 'memberships';
    }

    public function supportedPresets(): array
    {
        return ['generic'];
    }

    public function customFieldSentinel(): ?string
    {
        return '__custom_membership__';
    }

    public function columnMap(string $preset): array
    {
        return [
            'Membership Level / Tier'  => 'membership:tier',
            'Membership Status'        => 'membership:status',
            'Member Since'             => 'membership:starts_on',
            'Renewal Due / Expires On' => 'membership:expires_on',
            'Amount Paid'              => 'membership:amount_paid',
            'Notes'                    => 'membership:notes',
            'External ID'              => 'membership:external_id',
            'Email'                    => 'contact:email',
            'User ID'                  => 'contact:external_id',
            'Phone'                    => 'contact:phone',
        ];
    }

    public function headers(string $preset): array
    {
        $headers = [
            'Membership Level / Tier',
            'Membership Status',
            'Member Since',
            'Renewal Due / Expires On',
            'Amount Paid',
            'Notes',
            'External ID',
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
            ['header' => 'Membership Source', 'handle' => 'membership_source', 'type' => 'text'],
            ['header' => 'Auto Renew',        'handle' => 'auto_renew',        'type' => 'boolean'],
        ];
    }

    public function cleanRow(int $rowIndex, string $preset, Faker $faker): array
    {
        $first = $faker->firstName();
        $last  = $faker->lastName();
        $startsOn = $faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d');
        $expiresOn = date('Y-m-d', strtotime($startsOn . ' +1 year'));

        return [
            'Membership Level / Tier'  => $faker->randomElement(['Basic', 'Standard', 'Premium', 'Lifetime']),
            'Membership Status'         => 'active',
            'Member Since'              => $startsOn,
            'Renewal Due / Expires On'  => $expiresOn,
            'Amount Paid'               => (string) $faker->numberBetween(25, 500),
            'Notes'                     => $faker->sentence(),
            'External ID'               => 'MEM-FIX-' . $rowIndex,
            'Email'                     => strtolower("{$first}.{$last}+{$rowIndex}@example.org"),
            'User ID'                   => '',
            'Phone'                     => $faker->phoneNumber(),
            'Membership Source'         => $faker->randomElement(['web', 'event', 'referral']),
            'Auto Renew'                => $faker->boolean() ? 'Yes' : 'No',
        ];
    }
}
