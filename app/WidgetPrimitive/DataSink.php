<?php

namespace App\WidgetPrimitive;

use App\WidgetPrimitive\Exceptions\SourceRejectedException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

final class DataSink
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $payload
     */
    public function write(string $modelClass, string $source, array $payload): Model
    {
        if (! Source::isKnown($source)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown source [%s] passed to DataSink::write. Known sources: %s',
                $source,
                implode(', ', Source::KNOWN),
            ));
        }

        if (! in_array(HasSourcePolicy::class, class_uses_recursive($modelClass), true)) {
            throw new LogicException(sprintf(
                'Target [%s] does not use HasSourcePolicy — every DataSink write target must declare a source policy.',
                $modelClass,
            ));
        }

        $prototype = (new $modelClass)->forceFill($payload);

        if (! $prototype->acceptsSource($source)) {
            throw SourceRejectedException::for($modelClass, $source);
        }

        return $modelClass::create($payload);
    }
}
