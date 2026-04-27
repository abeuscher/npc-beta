<?php

namespace App\WidgetPrimitive\AmbientContexts;

use App\WidgetPrimitive\AmbientContext;
use Illuminate\Database\Eloquent\Model;

final class RecordDetailAmbientContext extends AmbientContext
{
    public function __construct(
        public readonly Model $record,
    ) {}
}
