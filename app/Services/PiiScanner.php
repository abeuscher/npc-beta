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
     * Column headers (normalised) whose cells should never trigger the 9-digit
     * ABA-routing / SSN-bare heuristic. US ZIP+4 without the hyphen is 9 digits
     * and would otherwise produce false positives.
     */
    private const ZIP_LIKE_HEADERS = [
        'zip',
        'zipcode',
        'postal',
        'postalcode',
        'postcode',
    ];

    public const DEFAULT_LIMIT = 50;

    /**
     * Scan a CSV file for PII / sensitive financial identifiers.
     *
     * Returns:
     *   [
     *     'header_violation' => bool,   // true when scan bailed on a blocked header (row scan never ran)
     *     'violations'       => [       // up to $limit rows
     *         [
     *             'reason'   => string,
     *             'detail'   => string,
     *             'row'      => int,
     *             'column'   => string,
     *             'row_data' => array,  // the entire original CSV row
     *         ],
     *         ...
     *     ],
     *     'truncated'        => bool,   // true if scan stopped at $limit
     *   ]
     */
    public function scan(string $filePath, array $headers, int $limit = self::DEFAULT_LIMIT): array
    {
        $headerViolation = $this->scanHeaders($headers);

        if ($headerViolation !== null) {
            return [
                'header_violation' => true,
                'violations' => [[
                    'reason'   => 'blocked_header',
                    'detail'   => "Column header \"{$headerViolation}\" matches a blocked sensitive-data field name.",
                    'row'      => 0,
                    'column'   => $headerViolation,
                    'row_data' => [],
                ]],
                'truncated' => false,
            ];
        }

        $zipColumnIndexes = $this->zipLikeColumnIndexes($headers);

        $handle = fopen($filePath, 'r');
        fgetcsv($handle, null, ',', '"', '\\'); // skip header

        $violations = [];
        $rowNumber  = 2;

        while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            foreach ($row as $colIndex => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $isZipColumn = in_array($colIndex, $zipColumnIndexes, true);
                $match       = $this->scanCell((string) $value, $isZipColumn);

                if ($match !== null) {
                    $columnLabel  = $headers[$colIndex] ?? 'column ' . ($colIndex + 1);
                    $violations[] = [
                        'reason'   => $match,
                        'detail'   => "Row {$rowNumber}, column \"{$columnLabel}\" appears to contain a {$match}.",
                        'row'      => $rowNumber,
                        'column'   => $columnLabel,
                        'row_data' => $row,
                    ];

                    if (count($violations) >= $limit) {
                        fclose($handle);

                        return [
                            'header_violation' => false,
                            'violations'       => $violations,
                            'truncated'        => true,
                        ];
                    }

                    // One violation per row is plenty — move on.
                    break;
                }
            }

            $rowNumber++;
        }

        fclose($handle);

        return [
            'header_violation' => false,
            'violations'       => $violations,
            'truncated'        => false,
        ];
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
     * Check a single cell value for sensitive data patterns. Pass
     * $isZipColumn = true to suppress the bare 9-digit heuristic (US ZIP+4 is
     * 9 digits and a chunk of valid ZIPs start with 01–12/21–32).
     * Returns a short string describing the match type, or null if clean.
     */
    public function scanCell(string $value, bool $isZipColumn = false): ?string
    {
        $stripped = preg_replace('/[\s\-]/', '', $value);

        // Credit card PAN: 13–19 digits passing Luhn. Always checked.
        if (preg_match('/^\d{13,19}$/', $stripped) && $this->luhn($stripped)) {
            return 'credit card number';
        }

        // SSN format (###-##-####). Hyphenated form is distinctive enough to
        // always check — a ZIP column wouldn't contain this shape.
        if (preg_match('/^\d{3}-\d{2}-\d{4}$/', $value)) {
            return 'Social Security Number';
        }

        // Bare 9-digit heuristic — suppressed on ZIP/postal columns where the
        // same shape is just a ZIP+4 without the hyphen.
        if (! $isZipColumn && preg_match('/^\d{9}$/', $stripped)) {
            $prefix = (int) substr($stripped, 0, 2);

            if (($prefix >= 1 && $prefix <= 12) || ($prefix >= 21 && $prefix <= 32)) {
                return 'ABA routing number';
            }

            return 'Social Security Number';
        }

        return null;
    }

    private function zipLikeColumnIndexes(array $headers): array
    {
        $indexes = [];

        foreach ($headers as $i => $header) {
            $normalized = strtolower(str_replace([' ', '_'], '', $header));

            if (in_array($normalized, self::ZIP_LIKE_HEADERS, true)) {
                $indexes[] = $i;
            }
        }

        return $indexes;
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
