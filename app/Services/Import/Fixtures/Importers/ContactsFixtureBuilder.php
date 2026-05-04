<?php

namespace App\Services\Import\Fixtures\Importers;

use App\Services\Import\Fixtures\FixtureBuilder;
use Faker\Generator as Faker;

class ContactsFixtureBuilder extends FixtureBuilder
{
    public function importer(): string
    {
        return 'contacts';
    }

    public function supportedPresets(): array
    {
        return ['generic', 'wild_apricot', 'bloomerang'];
    }

    public function defaultMatchKey(): string
    {
        return 'email';
    }

    public function contactMatchKey(): ?string
    {
        return null;
    }

    public function columnMap(string $preset): array
    {
        return match ($preset) {
            'wild_apricot' => [
                'First Name'    => 'first_name',
                'Last Name'     => 'last_name',
                'Email Address' => 'email',
                'Phone Number'  => 'phone',
                'Address'       => 'address_line_1',
                'City'          => 'city',
                'State'         => 'state',
                'Zip Code'      => 'postal_code',
                'Notes'         => 'notes',
            ],
            'bloomerang' => [
                'First'          => 'first_name',
                'Last'           => 'last_name',
                'Email'          => 'email',
                'Mobile Phone'   => 'phone',
                'Address Line 1' => 'address_line_1',
                'City'           => 'city',
                'State'          => 'state',
                'Zip'            => 'postal_code',
                'Notes'          => 'notes',
            ],
            default => [
                'First Name'     => 'first_name',
                'Last Name'      => 'last_name',
                'Email'          => 'email',
                'Phone'          => 'phone',
                'Address Line 1' => 'address_line_1',
                'City'           => 'city',
                'State'          => 'state',
                'Postal Code'    => 'postal_code',
                'Notes'          => 'notes',
            ],
        };
    }

    public function headers(string $preset): array
    {
        $headers = match ($preset) {
            'wild_apricot' => [
                'First Name', 'Last Name', 'Email Address', 'Phone Number',
                'Address', 'City', 'State', 'Zip Code', 'Notes',
            ],
            'bloomerang'   => [
                'First', 'Last', 'Email', 'Mobile Phone',
                'Address Line 1', 'City', 'State', 'Zip', 'Notes',
            ],
            default        => [
                'First Name', 'Last Name', 'Email', 'Phone',
                'Address Line 1', 'City', 'State', 'Postal Code', 'Notes',
            ],
        };

        foreach ($this->customFieldColumns($preset) as $cf) {
            $headers[] = $cf['header'];
        }

        return $headers;
    }

    public function customFieldColumns(string $preset): array
    {
        return match ($preset) {
            'wild_apricot' => [
                ['header' => 'Custom Field 1',  'handle' => 'industry',         'type' => 'text'],
                ['header' => 'Custom Field 2',  'handle' => 'donor_lifetime',   'type' => 'number'],
                ['header' => 'Custom Field 3',  'handle' => 'first_gift_date',  'type' => 'date'],
                ['header' => 'Custom Field 4',  'handle' => 'newsletter_opt_in', 'type' => 'boolean'],
                ['header' => 'Custom Field 5',  'handle' => 'volunteer_status', 'type' => 'select'],
                ['header' => 'Custom Field 6',  'handle' => 'biography',        'type' => 'rich_text'],
            ],
            'bloomerang' => [
                ['header' => 'Industry',           'handle' => 'industry',          'type' => 'text'],
                ['header' => 'Lifetime Giving',    'handle' => 'donor_lifetime',    'type' => 'number'],
                ['header' => 'First Gift Date',    'handle' => 'first_gift_date',   'type' => 'date'],
                ['header' => 'Newsletter Opt-In',  'handle' => 'newsletter_opt_in', 'type' => 'boolean'],
                ['header' => 'Volunteer Status',   'handle' => 'volunteer_status',  'type' => 'select'],
                ['header' => 'Biography',          'handle' => 'biography',         'type' => 'rich_text'],
            ],
            default => [
                ['header' => 'Industry',          'handle' => 'industry',          'type' => 'text'],
                ['header' => 'Lifetime Giving',   'handle' => 'donor_lifetime',    'type' => 'number'],
                ['header' => 'First Gift Date',   'handle' => 'first_gift_date',   'type' => 'date'],
                ['header' => 'Newsletter Opt-In', 'handle' => 'newsletter_opt_in', 'type' => 'boolean'],
                ['header' => 'Volunteer Status',  'handle' => 'volunteer_status',  'type' => 'select'],
                ['header' => 'Biography',         'handle' => 'biography',         'type' => 'rich_text'],
            ],
        };
    }

    public function cleanRow(int $rowIndex, string $preset, Faker $faker): array
    {
        $first = $faker->firstName();
        $last  = $faker->lastName();

        $base = match ($preset) {
            'wild_apricot' => [
                'First Name'    => $first,
                'Last Name'     => $last,
                'Email Address' => strtolower("{$first}.{$last}+{$rowIndex}@example.org"),
                'Phone Number'  => $faker->phoneNumber(),
                'Address'       => $faker->streetAddress(),
                'City'          => $faker->city(),
                'State'         => $faker->stateAbbr(),
                'Zip Code'      => $faker->postcode(),
                'Notes'         => $faker->sentence(),
            ],
            'bloomerang' => [
                'First'          => $first,
                'Last'           => $last,
                'Email'          => strtolower("{$first}.{$last}+{$rowIndex}@example.org"),
                'Mobile Phone'   => $faker->phoneNumber(),
                'Address Line 1' => $faker->streetAddress(),
                'City'           => $faker->city(),
                'State'          => $faker->stateAbbr(),
                'Zip'            => $faker->postcode(),
                'Notes'          => $faker->sentence(),
            ],
            default => [
                'First Name'     => $first,
                'Last Name'      => $last,
                'Email'          => strtolower("{$first}.{$last}+{$rowIndex}@example.org"),
                'Phone'          => $faker->phoneNumber(),
                'Address Line 1' => $faker->streetAddress(),
                'City'           => $faker->city(),
                'State'          => $faker->stateAbbr(),
                'Postal Code'    => $faker->postcode(),
                'Notes'          => $faker->sentence(),
            ],
        };

        $industry = $faker->randomElement(['Education', 'Healthcare', 'Technology', 'Arts', 'Government', 'Finance']);
        $volunteer = $faker->randomElement(['active', 'inactive', 'prospective']);

        $cfRow = match ($preset) {
            'wild_apricot' => [
                'Custom Field 1' => $industry,
                'Custom Field 2' => (string) $faker->numberBetween(50, 50000),
                'Custom Field 3' => $faker->date('Y-m-d'),
                'Custom Field 4' => $faker->boolean() ? 'Yes' : 'No',
                'Custom Field 5' => $volunteer,
                'Custom Field 6' => '<p>' . $faker->paragraph() . '</p>',
            ],
            'bloomerang' => [
                'Industry'           => $industry,
                'Lifetime Giving'    => (string) $faker->numberBetween(50, 50000),
                'First Gift Date'    => $faker->date('Y-m-d'),
                'Newsletter Opt-In'  => $faker->boolean() ? 'Yes' : 'No',
                'Volunteer Status'   => $volunteer,
                'Biography'          => '<p>' . $faker->paragraph() . '</p>',
            ],
            default => [
                'Industry'          => $industry,
                'Lifetime Giving'   => (string) $faker->numberBetween(50, 50000),
                'First Gift Date'   => $faker->date('Y-m-d'),
                'Newsletter Opt-In' => $faker->boolean() ? 'Yes' : 'No',
                'Volunteer Status'  => $volunteer,
                'Biography'         => '<p>' . $faker->paragraph() . '</p>',
            ],
        };

        return array_merge($base, $cfRow);
    }
}
