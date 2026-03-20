<?php

namespace App\Services;

class PiiScanner
{
    /**
     * Column header names that indicate sensitive data (normalised: lowercase,
     * spaces and underscores stripped before comparison).
     */
    private const BLOCKED_HEADERS = [
        'ssn',
        'socialsecurity',
        'socialsecuritynumber',
        'creditcard',
        'creditcardnumber',
        'cardnumber',
        'pan',
        'routingnumber',
        'accountnumber',
        'bankaccount',
        'abarouting',
        'driverlicense',
        'driverslicense',
        'dlnumber',
    ];

    /**
     * Scan a CSV file for PII / sensitive financial identifiers.
     *
     * Returns null when the file is clean.
     * Returns an array with 'reason', 'detail', and optionally 'row'/'column'
     * when a violation is found.
     */
    public function scan(string $filePath, array $headers): ?array
    {
        // Layer 1 — column header blocklist
        $headerViolation = $this->scanHeaders($headers);

        if ($headerViolation !== null) {
            return [
                'reason' => 'blocked_header',
                'detail' => "Column header \"{$headerViolation}\" matches a blocked sensitive-data field name.",
            ];
        }

        // Layer 2 — cell content patterns
        $handle = fopen($filePath, 'r');

        // Skip header row
        fgetcsv($handle);

        $rowNumber = 2;

        while (($row = fgetcsv($handle)) !== false) {
            foreach ($row as $colIndex => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $match = $this->scanCell($value);

                if ($match !== null) {
                    fclose($handle);

                    $columnLabel = $headers[$colIndex] ?? "column " . ($colIndex + 1);

                    return [
                        'reason'  => $match,
                        'detail'  => "Row {$rowNumber}, column \"{$columnLabel}\" appears to contain a {$match}.",
                        'row'     => $rowNumber,
                        'column'  => $columnLabel,
                    ];
                }
            }

            $rowNumber++;
        }

        fclose($handle);

        return null;
    }

    /**
     * Check a set of column headers against the blocklist.
     * Returns the first offending header string, or null if clean.
     */
    public function scanHeaders(array $headers): ?string
    {
        foreach ($headers as $header) {
            $normalised = strtolower(str_replace([' ', '_'], '', $header));

            if (in_array($normalised, self::BLOCKED_HEADERS, true)) {
                return $header;
            }
        }

        return null;
    }

    /**
     * Check a single cell value for sensitive data patterns.
     * Returns a short string describing the match type, or null if clean.
     */
    public function scanCell(string $value): ?string
    {
        $stripped = preg_replace('/[\s\-]/', '', $value);

        // Credit card PAN: 13–19 digits passing Luhn check
        if (preg_match('/^\d{13,19}$/', $stripped) && $this->luhn($stripped)) {
            return 'credit card number';
        }

        // SSN: ###-##-####
        if (preg_match('/^\d{3}-\d{2}-\d{4}$/', $value)) {
            return 'Social Security Number';
        }

        // 9-digit sequences: ABA routing (prefix 01–12 or 21–32) or SSN
        if (preg_match('/^\d{9}$/', $stripped)) {
            $prefix = (int) substr($stripped, 0, 2);

            if (($prefix >= 1 && $prefix <= 12) || ($prefix >= 21 && $prefix <= 32)) {
                return 'ABA routing number';
            }

            return 'Social Security Number';
        }

        return null;
    }

    private function luhn(string $number): bool
    {
        $sum  = 0;
        $flip = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($flip) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum  += $digit;
            $flip  = ! $flip;
        }

        return $sum % 10 === 0;
    }
}
