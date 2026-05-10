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

            $modelType = Str::snake(class_basename($model::class));

            $richTextHandles = CustomFieldDef::query()
                ->where('model_type', $modelType)
                ->where('field_type', 'rich_text')
                ->pluck('handle')
                ->all();

            if ($richTextHandles === []) {
                return;
            }

            foreach ($richTextHandles as $handle) {
                if (isset($custom[$handle]) && is_string($custom[$handle])) {
                    $custom[$handle] = HtmlSanitizer::sanitize($custom[$handle]);
                }
            }

            $model->custom_fields = $custom;
        });
    }
}
