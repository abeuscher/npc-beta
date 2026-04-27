<?php

use App\WidgetPrimitive\AmbientContext;
use App\WidgetPrimitive\AmbientContexts\DashboardAmbientContext;

it('constructs without args and is an instance of AmbientContext', function () {
    $ambient = new DashboardAmbientContext();

    expect($ambient)->toBeInstanceOf(AmbientContext::class);
});
