<?php

namespace App\Services\Import;

/**
 * Heuristic detection for columns whose values are procedural metadata rather
 * than real data. Called during the mapping step to default noise columns to
 * unmapped.
 *
 * Patterns detected:
 * - Wild Apricot `Field&&Visibility` concatenations (multi-line FieldName&&Level)
 * - Very long metadata strings (avg >200 chars with no clear delimiter)
 * - Structured key-value dumps (repeated key: value or key=value across lines)
 */
class NoiseDetector
{
    /**
     * Returns true if the sample values for a single column look like
     * procedural system metadata rather than meaningful user data.
     *
     * @param array $sampleValues Up to 10 cell values from a single column
     */
    public static function detect(array $sampleValues): bool
    {
        $nonBlank = array_filter($sampleValues, fn ($v) => filled($v));

        if (count($nonBlank) < 2) {
            return false;
        }

        if (static::isFieldVisibilityConcatenation($nonBlank)) {
            return true;
        }

        if (static::isLongMetadataString($nonBlank)) {
            return true;
        }

        if (static::isKeyValueDump($nonBlank)) {
            return true;
        }

        return false;
    }

    /**
     * Wild Apricot `Field&&Visibility` pattern. Cells contain newline-separated
     * `FieldName&&VisibilityLevel` pairs.
     */
    private static function isFieldVisibilityConcatenation(array $values): bool
    {
        $matchCount = 0;

        foreach ($values as $value) {
            $lines = preg_split('/[\r\n]+/', (string) $value);

            if (count($lines) < 2) {
                continue;
            }

            $fvLines = 0;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '' && preg_match('/^[\w\s]+&&\w+$/', $line)) {
                    $fvLines++;
                }
            }

            if ($fvLines >= 2) {
                $matchCount++;
            }
        }

        // If most non-blank samples match, it's noise
        return $matchCount >= ceil(count($values) * 0.5);
    }

    /**
     * Very long metadata strings. Average >200 chars across sample rows
     * with no obvious delimiter structure.
     */
    private static function isLongMetadataString(array $values): bool
    {
        $totalLen = 0;

        foreach ($values as $value) {
            $totalLen += strlen((string) $value);
        }

        $avgLen = $totalLen / count($values);

        return $avgLen > 200;
    }

    /**
     * Structured key-value dumps. Multiple lines matching `key: value` or
     * `key=value` patterns.
     */
    private static function isKeyValueDump(array $values): bool
    {
        $matchCount = 0;

        foreach ($values as $value) {
            $lines = preg_split('/[\r\n]+/', (string) $value);

            if (count($lines) < 3) {
                continue;
            }

            $kvLines = 0;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '' && (
                    preg_match('/^\w[\w\s]*:\s+.+$/', $line) ||
                    preg_match('/^\w[\w\s]*=.+$/', $line)
                )) {
                    $kvLines++;
                }
            }

            if ($kvLines >= 3) {
                $matchCount++;
            }
        }

        return $matchCount >= ceil(count($values) * 0.5);
    }
}
