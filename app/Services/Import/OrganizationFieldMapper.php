<?php

namespace App\Services\Import;

class OrganizationFieldMapper
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
            // organization:name
            'name'                       => 'organization:name',
            'organization'               => 'organization:name',
            'organization name'          => 'organization:name',
            'organization_name'          => 'organization:name',
            'organizationname'           => 'organization:name',
            'org'                        => 'organization:name',
            'org name'                   => 'organization:name',
            'org_name'                   => 'organization:name',
            'company'                    => 'organization:name',
            'company name'               => 'organization:name',
            'company_name'               => 'organization:name',
            'companyname'                => 'organization:name',

            // organization:type
            'type'                       => 'organization:type',
            'organization type'          => 'organization:type',
            'organization_type'          => 'organization:type',
            'organizationtype'           => 'organization:type',
            'org type'                   => 'organization:type',
            'org_type'                   => 'organization:type',

            // organization:industry
            'industry'                   => 'organization:industry',
            'organization industry'      => 'organization:industry',
            'organization_industry'      => 'organization:industry',
            'sector'                     => 'organization:industry',

            // organization:ein
            'ein'                        => 'organization:ein',
            'organization ein'           => 'organization:ein',
            'organization_ein'           => 'organization:ein',
            'tax id'                     => 'organization:ein',
            'tax_id'                     => 'organization:ein',
            'taxid'                      => 'organization:ein',
            'tax id number'              => 'organization:ein',

            // organization:website
            'website'                    => 'organization:website',
            'web site'                   => 'organization:website',
            'web_site'                   => 'organization:website',
            'url'                        => 'organization:website',
            'organization website'       => 'organization:website',
            'organization_website'       => 'organization:website',

            // organization:phone
            'phone'                      => 'organization:phone',
            'phone number'               => 'organization:phone',
            'phone_number'               => 'organization:phone',
            'phonenumber'                => 'organization:phone',
            'organization phone'         => 'organization:phone',
            'organization_phone'         => 'organization:phone',

            // organization:email
            'email'                      => 'organization:email',
            'email address'              => 'organization:email',
            'email_address'              => 'organization:email',
            'emailaddress'               => 'organization:email',
            'e-mail'                     => 'organization:email',
            'organization email'         => 'organization:email',
            'organization_email'         => 'organization:email',

            // organization:address_line_1
            'address'                    => 'organization:address_line_1',
            'address line 1'             => 'organization:address_line_1',
            'address_line_1'             => 'organization:address_line_1',
            'addressline1'               => 'organization:address_line_1',
            'address 1'                  => 'organization:address_line_1',
            'address1'                   => 'organization:address_line_1',
            'street'                     => 'organization:address_line_1',
            'street address'            => 'organization:address_line_1',
            'streetaddress'              => 'organization:address_line_1',

            // organization:address_line_2
            'address line 2'             => 'organization:address_line_2',
            'address_line_2'             => 'organization:address_line_2',
            'addressline2'               => 'organization:address_line_2',
            'address 2'                  => 'organization:address_line_2',
            'address2'                   => 'organization:address_line_2',
            'suite'                      => 'organization:address_line_2',

            // organization:city
            'city'                       => 'organization:city',
            'town'                       => 'organization:city',

            // organization:state
            'state'                      => 'organization:state',
            'province'                   => 'organization:state',
            'region'                     => 'organization:state',
            'state/province'             => 'organization:state',
            'stateprovince'              => 'organization:state',

            // organization:postal_code
            'postal code'                => 'organization:postal_code',
            'postal_code'                => 'organization:postal_code',
            'postalcode'                 => 'organization:postal_code',
            'zip'                        => 'organization:postal_code',
            'zip code'                   => 'organization:postal_code',
            'zip_code'                   => 'organization:postal_code',
            'zipcode'                    => 'organization:postal_code',
            'postcode'                   => 'organization:postal_code',

            // organization:country
            'country'                    => 'organization:country',

            // organization:external_id
            'external id'                => 'organization:external_id',
            'external_id'                => 'organization:external_id',
            'externalid'                 => 'organization:external_id',
            'id'                         => 'organization:external_id',
            'organization id'            => 'organization:external_id',
            'organization_id'            => 'organization:external_id',
            'org id'                     => 'organization:external_id',
            'org_id'                     => 'organization:external_id',
        ];
    }

    private static function wildApricotMap(): array
    {
        return [
            'name'              => 'organization:name',
            'organization'      => 'organization:name',
            'organization name' => 'organization:name',
            'company'           => 'organization:name',
            'company name'      => 'organization:name',

            'email'             => 'organization:email',
            'email address'     => 'organization:email',
            'phone'             => 'organization:phone',
            'phone number'      => 'organization:phone',
            'website'           => 'organization:website',

            'address'           => 'organization:address_line_1',
            'address 2'         => 'organization:address_line_2',
            'city'              => 'organization:city',
            'state'             => 'organization:state',
            'zip code'          => 'organization:postal_code',
            'country'           => 'organization:country',
        ];
    }

    private static function bloomerangMap(): array
    {
        return [
            'name'              => 'organization:name',
            'organization'      => 'organization:name',
            'organization name' => 'organization:name',
            'company'           => 'organization:name',
            'company name'      => 'organization:name',

            'email'             => 'organization:email',
            'email address'     => 'organization:email',
            'phone'             => 'organization:phone',
            'phone number'      => 'organization:phone',
            'website'           => 'organization:website',

            'address line 1'    => 'organization:address_line_1',
            'address line 2'    => 'organization:address_line_2',
            'city'              => 'organization:city',
            'state'             => 'organization:state',
            'zip'               => 'organization:postal_code',
            'country'           => 'organization:country',
        ];
    }
}
