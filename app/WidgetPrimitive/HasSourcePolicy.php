<?php

namespace App\WidgetPrimitive;

trait HasSourcePolicy
{
    public function acceptsSource(string $source): bool
    {
        if ($source === Source::HUMAN) {
            return true;
        }

        if (! Source::isKnown($source)) {
            return false;
        }

        if (! defined(static::class . '::ACCEPTED_SOURCES')) {
            return false;
        }

        return in_array($source, static::ACCEPTED_SOURCES, true);
    }
}
