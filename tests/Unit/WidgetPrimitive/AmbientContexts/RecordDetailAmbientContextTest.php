<?php

use App\WidgetPrimitive\AmbientContext;
use App\WidgetPrimitive\AmbientContexts\RecordDetailAmbientContext;

it('constructs without args and is an instance of AmbientContext', function () {
    $ambient = new RecordDetailAmbientContext();

    expect($ambient)->toBeInstanceOf(AmbientContext::class);
});
