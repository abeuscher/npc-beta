<?php

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class);

it('schedules media-library:clean daily', function () {
    Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($output)->toContain('media-library:clean');
    expect($output)->toMatch('/0\s+0\s+\*\s+\*\s+\*\s+php artisan media-library:clean/');
});
