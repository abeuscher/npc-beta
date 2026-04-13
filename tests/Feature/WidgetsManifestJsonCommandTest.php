<?php

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class);

it('widgets:manifest-json exits 0 and prints a JSON-decodable widget map', function () {
    $exit = Artisan::call('widgets:manifest-json');
    $output = Artisan::output();

    expect($exit)->toBe(0);

    $decoded = json_decode($output, true);
    expect($decoded)->toBeArray();
    expect($decoded)->not->toBeEmpty();
    expect($decoded)->toHaveKey('text_block');
});
