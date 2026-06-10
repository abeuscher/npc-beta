<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value is either blank or a JSON object/array (a valid
 * JSON-LD shape). Mirrors the render-time gate in
 * SeoMetaGenerator::safeJsonLd so the operator gets immediate feedback rather
 * than silent non-emission — a scalar or malformed string is rejected.
 */
class ValidJsonLd implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            $fail('The :attribute must be a valid JSON-LD object or array (for example a SoftwareApplication, Organization, or Person graph).');
        }
    }
}
