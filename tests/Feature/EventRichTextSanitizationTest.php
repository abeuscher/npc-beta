<?php

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sanitises Event.description on save', function () {
    $event = Event::factory()->create([
        'description' => '<p>Welcome.</p><script>alert(1)</script><img src="javascript:bad" alt="x">',
    ]);

    expect($event->fresh()->description)
        ->toBe('<p>Welcome.</p><img alt="x">');
});

it('sanitises Event.meeting_details on save', function () {
    $event = Event::factory()->virtual()->create([
        'meeting_details' => '<p onclick="alert(1)">Join here</p><iframe src="https://evil.com"></iframe>',
    ]);

    expect($event->fresh()->meeting_details)->toBe('<p>Join here</p>');
});

it('preserves inline image storage src on Event.description', function () {
    $event = Event::factory()->create([
        'description' => '<p>Photo: <img src="/storage/1/photo.jpg" alt="caption" style="width: 50%;"></p>',
    ]);

    expect($event->fresh()->description)
        ->toBe('<p>Photo: <img src="/storage/1/photo.jpg" alt="caption" style="width: 50%;"></p>');
});
