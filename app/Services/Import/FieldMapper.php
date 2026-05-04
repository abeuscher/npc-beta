<?php

namespace App\Services\Import;

class FieldMapper
{
    /**
     * Headers (normalized: lowercased + trimmed) that must never be mapped or
     * auto-promoted to a custom field. Sensitive or auth-only — we never want
     * these landing in the CRM.
     */
    public const SKIPPED_HEADERS = [
        'password',
        'pwd',
        'passwd',
        'pass',
        'password_hash',
        'password hash',
        'passwordhash',
    ];

    /**
     * Return the canonical destination field for a source column header.
     * Returns null if the column should be ignored.
     */
    public function map(string $sourceColumn, string $preset = 'generic'): ?string
    {
        $normalized = strtolower(trim($sourceColumn));

        if (static::isSkipped($normalized)) {
            return null;
        }

        $map = static::presetMap($preset);

        return $map[$normalized] ?? null;
    }

    /**
     * Per-source skip-header arrays. Merged with the global SKIPPED_HEADERS
     * when a source preset is selected.
     */
    private const SOURCE_SKIPPED_HEADERS = [
        'generic'      => [],
        'bloomerang'   => [],
        'wild_apricot' => [
            'password',
            'archived',
            'subscribed to emails',
            'subscription source',
            'opted in',
            'administration access',
            'profile last updated',
            'last login',
            'updated by',
            'access to profile by others',
            'details to show',
            'photo albums enabled',
            'member bundle id or email',
            'member role',
            'group participation',
        ],
    ];

    public static function isSkipped(string $normalizedHeader, ?string $sourcePreset = null): bool
    {
        return in_array($normalizedHeader, static::sourceSkippedHeaders($sourcePreset), true);
    }

    /**
     * Return the full skip-header list for a given source preset, merging the
     * global baseline with source-specific headers.
     */
    public static function sourceSkippedHeaders(?string $sourcePreset = null): array
    {
        $global = static::SKIPPED_HEADERS;

        if ($sourcePreset === null) {
            return $global;
        }

        $sourceSpecific = static::SOURCE_SKIPPED_HEADERS[$sourcePreset] ?? [];

        return array_values(array_unique(array_merge($global, $sourceSpecific)));
    }

    /**
     * Values that should be treated as blank/null. Common placeholders in
     * Wild Apricot, Bloomerang, and other CRM exports.
     */
    private const NULL_SYNONYMS = [
        'n/a', 'na', 'n.a.', 'none', 'null', '-', '--', '—', 'undefined',
    ];

    /**
     * Normalize a raw cell value: trim whitespace, treat empty strings and
     * common null-synonym placeholders as null.
     */
    public static function normalizeValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        if (in_array(strtolower($trimmed), self::NULL_SYNONYMS, true)) {
            return null;
        }

        return $trimmed;
    }

    /**
     * Map a human-readable source name to its preset key.
     * Returns null if the source name doesn't match a known preset.
     */
    public static function presetFromSourceName(?string $sourceName): ?string
    {
        if (! $sourceName) {
            return null;
        }

        $normalized = strtolower(trim($sourceName));

        return match ($normalized) {
            'wild apricot' => 'wild_apricot',
            'bloomerang'   => 'bloomerang',
            'generic csv'  => 'generic',
            default        => null,
        };
    }

    /**
     * Return all available preset names.
     */
    public static function presets(): array
    {
        return ['generic', 'wild_apricot', 'bloomerang'];
    }

    /**
     * Return the full mapping array for a preset.
     * Keys = source column headers (lowercase, trimmed), values = Contact field names.
     */
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
            'first_name'      => 'first_name',
            'first name'      => 'first_name',
            'firstname'       => 'first_name',
            'fname'           => 'first_name',
            'given name'      => 'first_name',
            'givenname'       => 'first_name',
            'last_name'       => 'last_name',
            'last name'       => 'last_name',
            'lastname'        => 'last_name',
            'surname'         => 'last_name',
            'lname'           => 'last_name',
            'family name'     => 'last_name',
            'familyname'      => 'last_name',
            'prefix'          => 'prefix',
            'salutation'      => 'prefix',
            'email'           => 'email',
            'email address'   => 'email',
            'emailaddress'    => 'email',
            'e-mail'          => 'email',
            'primary email'   => 'email',
            'primaryemail'    => 'email',
            'phone'           => 'phone',
            'phone number'    => 'phone',
            'phonenumber'     => 'phone',
            'mobile'          => 'phone',
            'mobile phone'    => 'phone',
            'mobilephone'     => 'phone',
            'cell'            => 'phone',
            'cell phone'      => 'phone',
            'cellphone'       => 'phone',
            'home phone'      => 'phone',
            'homephone'       => 'phone',
            'work phone'      => 'phone',
            'workphone'       => 'phone',
            'telephone'       => 'phone',
            'address'         => 'address_line_1',
            'address_line_1'  => 'address_line_1',
            'address line 1'  => 'address_line_1',
            'addressline1'    => 'address_line_1',
            'address1'        => 'address_line_1',
            'street'          => 'address_line_1',
            'street address'  => 'address_line_1',
            'streetaddress'   => 'address_line_1',
            'address_line_2'  => 'address_line_2',
            'address line 2'  => 'address_line_2',
            'addressline2'    => 'address_line_2',
            'address 2'       => 'address_line_2',
            'address2'        => 'address_line_2',
            'apt'             => 'address_line_2',
            'apartment'       => 'address_line_2',
            'city'            => 'city',
            'town'            => 'city',
            'state'           => 'state',
            'province'        => 'state',
            'region'          => 'state',
            'state/province'  => 'state',
            'stateprovince'   => 'state',
            'zip'             => 'postal_code',
            'zip code'        => 'postal_code',
            'zipcode'         => 'postal_code',
            'zip_code'        => 'postal_code',
            'postal_code'     => 'postal_code',
            'postal code'     => 'postal_code',
            'postalcode'      => 'postal_code',
            'postcode'        => 'postal_code',
            'country'         => 'country',
            'date of birth'   => 'date_of_birth',
            'date_of_birth'   => 'date_of_birth',
            'dateofbirth'     => 'date_of_birth',
            'dob'             => 'date_of_birth',
            'birthday'        => 'date_of_birth',
            'birth date'      => 'date_of_birth',
            'birthdate'       => 'date_of_birth',
            'notes'           => 'notes',
        ];
    }

    private static function wildApricotMap(): array
    {
        return [
            // Custom profile form fields (member-submitted data)
            'first name'      => 'first_name',
            'last name'       => 'last_name',
            'email'           => 'email',
            'phone'           => 'phone',
            'address'         => 'address_line_1',
            'address 2'       => 'address_line_2',
            'city'            => 'city',
            'state'           => 'state',
            'zip code'        => 'postal_code',
            'notes'           => 'notes',
            // Wild Apricot system fields (CRM-level contact record)
            'firstname'       => 'first_name',
            'lastname'        => 'last_name',
            'email address'   => 'email',
            'phone number'    => 'phone',
        ];
    }

    private static function bloomerangMap(): array
    {
        return [
            'first'          => 'first_name',
            'last'           => 'last_name',
            'email'          => 'email',
            'mobile phone'   => 'phone',
            'home phone'     => 'phone',
            'address line 1' => 'address_line_1',
            'address line 2' => 'address_line_2',
            'city'           => 'city',
            'state'          => 'state',
            'zip'            => 'postal_code',
            'notes'          => 'notes',
        ];
    }
}
