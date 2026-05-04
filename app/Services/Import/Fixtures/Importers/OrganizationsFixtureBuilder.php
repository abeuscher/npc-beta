<?php

namespace App\Services\Import\Fixtures\Importers;

use App\Services\Import\Fixtures\FixtureBuilder;
use Faker\Generator as Faker;

class OrganizationsFixtureBuilder extends FixtureBuilder
{
    public function importer(): string
    {
        return 'organizations';
    }

    public function supportedPresets(): array
    {
        return ['generic'];
    }

    public function defaultMatchKey(): string
    {
        return 'name';
    }

    public function contactMatchKey(): ?string
    {
        return null;
    }

    public function customFieldSentinel(): ?string
    {
        return '__custom_organization__';
    }

    public function columnMap(string $preset): array
    {
        return [
            'Name'           => 'organization:name',
            'Type'           => 'organization:type',
            'Website'        => 'organization:website',
            'Phone'          => 'organization:phone',
            'Email'          => 'organization:email',
            'Address Line 1' => 'organization:address_line_1',
            'Address Line 2' => 'organization:address_line_2',
            'City'           => 'organization:city',
            'State'          => 'organization:state',
            'Postal Code'    => 'organization:postal_code',
            'Country'        => 'organization:country',
            'External ID'    => 'organization:external_id',
        ];
    }

    public function headers(string $preset): array
    {
        $headers = [
            'Name', 'Type', 'Website', 'Phone', 'Email',
            'Address Line 1', 'Address Line 2', 'City', 'State', 'Postal Code', 'Country',
            'External ID',
        ];

        foreach ($this->customFieldColumns($preset) as $cf) {
            $headers[] = $cf['header'];
        }

        return $headers;
    }

    public function customFieldColumns(string $preset): array
    {
        return [
            ['header' => 'Industry',         'handle' => 'industry',     'type' => 'text'],
            ['header' => 'Founded Year',     'handle' => 'founded_year', 'type' => 'number'],
            ['header' => 'Mission Statement', 'handle' => 'mission',     'type' => 'rich_text'],
        ];
    }

    public function cleanRow(int $rowIndex, string $preset, Faker $faker): array
    {
        $name = $faker->company();

        return [
            'Name'           => $name,
            'Type'           => $faker->randomElement(['nonprofit', 'for_profit', 'government', 'other']),
            'Website'        => 'https://' . $faker->domainName(),
            'Phone'          => $faker->phoneNumber(),
            'Email'          => 'info@' . $faker->domainName(),
            'Address Line 1' => $faker->streetAddress(),
            'Address Line 2' => $faker->boolean(30) ? 'Suite ' . $faker->numberBetween(100, 999) : '',
            'City'           => $faker->city(),
            'State'          => $faker->stateAbbr(),
            'Postal Code'    => $faker->postcode(),
            'Country'        => 'USA',
            'External ID'    => 'ORG-FIX-' . $rowIndex,
            'Industry'       => $faker->randomElement(['Education', 'Healthcare', 'Technology', 'Arts', 'Government']),
            'Founded Year'   => (string) $faker->numberBetween(1900, 2024),
            'Mission Statement' => '<p>' . $faker->paragraph() . '</p>',
        ];
    }
}
