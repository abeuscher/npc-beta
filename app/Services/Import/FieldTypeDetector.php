<?php

namespace App\Services\Import;

class FieldTypeDetector
{
    /**
     * Infer a custom-field type from a handful of sample values.
     * Returns one of: 'text', 'number', 'date', 'boolean'. Empty or all-blank
     * input falls through to 'text'.
     */
    public static function detect(array $sampleValues): string
    {
        $nonEmpty = array_values(array_filter(array_map(
            fn ($v) => is_string($v) ? trim($v) : $v,
            $sampleValues
        ), fn ($v) => $v !== null && $v !== ''));

        if (count($nonEmpty) === 0) {
            return 'text';
        }

        if (self::allMatch($nonEmpty, '/^(true|false|yes|no|y|n|0|1)$/i')) {
            return 'boolean';
        }

        if (self::allMatch($nonEmpty, '/^-?\d+(\.\d+)?$/')) {
            return 'number';
        }

        if (self::allLookLikeDates($nonEmpty)) {
            return 'date';
        }

        return 'text';
    }

    private static function allMatch(array $values, string $pattern): bool
    {
        foreach ($values as $value) {
            if (! preg_match($pattern, (string) $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Accepts ISO-ish, US, and European layouts. Uses strtotime as a fallback
     * after a shape check so obvious numbers (e.g. "42") aren't interpreted as
     * Unix timestamps.
     */
    private static function allLookLikeDates(array $values): bool
    {
        $dateShape = '/^\d{1,4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,4}(\s+\d{1,2}:\d{2}(:\d{2})?)?$/';

        foreach ($values as $value) {
            $str = (string) $value;

            if (! preg_match($dateShape, $str)) {
                return false;
            }

            if (strtotime($str) === false) {
                return false;
            }
        }

        return true;
    }
}
