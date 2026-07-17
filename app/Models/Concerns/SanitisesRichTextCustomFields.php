<?php

namespace App\Models\Concerns;

use App\Models\CustomFieldDef;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Str;

trait SanitisesRichTextCustomFields
{
    protected static function bootSanitisesRichTextCustomFields(): void
    {
        static::saving(function ($model) {
            $custom = $model->custom_fields ?? [];
            if (! is_array($custom) || $custom === []) {
                return;
            }

            $model->custom_fields = static::sanitizeRichTextCustomFields($custom);
        });
    }

    /**
     * Apply HtmlSanitizer to this model type's rich-text custom-field values.
     *
     * Exposed as a static helper so write paths that suppress model events —
     * imports using EventRegistration::withoutEvents(), saveQuietly(), etc. —
     * can funnel through the same sanitizer the saving() hook applies. Non-array
     * or empty input is returned unchanged.
     *
     * @param  array<string, mixed>  $custom
     * @return array<string, mixed>
     */
    public static function sanitizeRichTextCustomFields(array $custom): array
    {
        if ($custom === []) {
            return $custom;
        }

        $richTextHandles = CustomFieldDef::query()
            ->where('model_type', Str::snake(class_basename(static::class)))
            ->where('field_type', 'rich_text')
            ->pluck('handle')
            ->all();

        foreach ($richTextHandles as $handle) {
            if (isset($custom[$handle]) && is_string($custom[$handle])) {
                $custom[$handle] = HtmlSanitizer::sanitize($custom[$handle]);
            }
        }

        return $custom;
    }
}
