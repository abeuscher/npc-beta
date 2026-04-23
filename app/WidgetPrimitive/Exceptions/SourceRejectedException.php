<?php

namespace App\WidgetPrimitive\Exceptions;

use RuntimeException;

class SourceRejectedException extends RuntimeException
{
    public static function for(string $modelClass, string $source): self
    {
        return new self(sprintf(
            'Source [%s] rejected by target [%s] — not present in ACCEPTED_SOURCES.',
            $source,
            $modelClass,
        ));
    }
}
