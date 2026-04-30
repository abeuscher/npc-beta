<?php

namespace App\WidgetPrimitive\Exceptions;

use LogicException;

class ScrubSourceLocked extends LogicException
{
    public static function for(string $modelClass, mixed $key, ?string $attemptedSource): self
    {
        return new self(sprintf(
            'Cannot mutate source on [%s:%s]: source is locked at scrub_data (attempted [%s]).',
            $modelClass,
            $key ?? 'null',
            $attemptedSource ?? 'null',
        ));
    }
}
