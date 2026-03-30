<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidHtmlSnippet implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $wrapped = '<html><body>' . $value . '</body></html>';

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        // Filter out minor warnings — only fail on actual parse errors
        $realErrors = array_filter($errors, fn ($e) => $e->level >= LIBXML_ERR_ERROR);

        if (! empty($realErrors)) {
            $fail('The :attribute contains malformed HTML.');
        }
    }
}
