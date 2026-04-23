<?php

use App\WidgetPrimitive\ContentType;
use App\WidgetPrimitive\Source;
use App\Widgets\TextBlock\TextBlockDefinition;

it('WidgetDefinition::acceptedSources() defaults to [Source::HUMAN]', function () {
    $def = new TextBlockDefinition;

    expect($def->acceptedSources())->toBe([Source::HUMAN]);
});

it('ContentType defaults its accepts property to [Source::HUMAN] when omitted', function () {
    $ct = new ContentType(
        handle: 'example.shape',
        fields: [['key' => 'title', 'type' => 'text']],
    );

    expect($ct->accepts)->toBe([Source::HUMAN]);
});

it('ContentType carries the accepts value passed in the constructor', function () {
    $ct = new ContentType(
        handle: 'example.shape',
        fields: [['key' => 'title', 'type' => 'text']],
        accepts: [Source::HUMAN, Source::DEMO],
    );

    expect($ct->accepts)->toBe([Source::HUMAN, Source::DEMO]);
});
