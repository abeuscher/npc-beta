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

    public static function isSkipped(string $normalizedHeader): bool
    {
        return in_array($normalizedHeader, static::SKIPPED_HEADERS, true);
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
            'last_name'       => 'last_name',
            'last name'       => 'last_name',
            'surname'         => 'last_name',
            'email'           => 'email',
            'email address'   => 'email',
            'phone'           => 'phone',
            'phone number'    => 'phone',
            'mobile'          => 'phone',
            'address'         => 'address_line_1',
            'address_line_1'  => 'address_line_1',
            'address line 1'  => 'address_line_1',
            'street'          => 'address_line_1',
            'address_line_2'  => 'address_line_2',
            'address line 2'  => 'address_line_2',
            'address 2'       => 'address_line_2',
            'city'            => 'city',
            'town'            => 'city',
            'state'           => 'state',
            'province'        => 'state',
            'region'          => 'state',
            'zip'             => 'postal_code',
            'zip code'        => 'postal_code',
            'postal_code'     => 'postal_code',
            'postcode'        => 'postal_code',
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
