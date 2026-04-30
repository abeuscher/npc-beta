<?php

namespace App\WidgetPrimitive;

use App\WidgetPrimitive\Exceptions\ScrubSourceLocked;

trait EnforcesScrubInheritance
{
    public static function bootEnforcesScrubInheritance(): void
    {
        static::creating(function ($model) {
            if ($model->source === Source::SCRUB_DATA) {
                return;
            }

            foreach (static::scrubInheritsFrom() as $key => $entry) {
                $parent = static::resolveScrubParent($model, $key, $entry);

                if ($parent !== null && $parent->source === Source::SCRUB_DATA) {
                    $model->source = Source::SCRUB_DATA;
                    return;
                }
            }
        });

        static::updating(function ($model) {
            if ($model->getOriginal('source') === Source::SCRUB_DATA
                && $model->source !== Source::SCRUB_DATA) {
                throw ScrubSourceLocked::for(static::class, $model->getKey(), $model->source);
            }
        });
    }

    public static function scrubInheritsFrom(): array
    {
        return [];
    }

    protected static function resolveScrubParent($model, $key, $entry)
    {
        if (is_array($entry) && isset($entry['type'], $entry['id'])) {
            $class = $model->{$entry['type']};
            $id    = $model->{$entry['id']};

            if (! $class || ! $id || ! class_exists($class)) {
                return null;
            }

            return $class::find($id);
        }

        $id = $model->{$key};
        if (! $id) {
            return null;
        }

        return $entry::find($id);
    }
}
