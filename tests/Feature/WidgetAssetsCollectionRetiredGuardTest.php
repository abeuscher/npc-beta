<?php

// Session A002 / Phase 1 — guard against re-introduction of the dead
// `$widgetAssets` plumbing retired in this session. The previous shape
// collected widget CSS/JS/SCSS asset paths into an accumulator threaded
// through PageBlockRenderer / ChromeRenderer / PageController /
// PostController, but no view ever consumed it. ~20 lines of inert
// reference plumbing. Removed; this test makes sure it stays removed.
//
// Pure source-grep guard. Cheap. The cost of a false positive (someone
// adds a legitimately-named `widgetAssets` local for an unrelated
// purpose) is a forced re-read of the session-A002 close notes before
// editing this list — acceptable friction.

use Tests\TestCase;

uses(TestCase::class);

it('WidgetRenderer no longer declares a collectAssets method', function () {
    $src = file_get_contents(app_path('Services/WidgetRenderer.php'));
    expect($src)->not->toContain('function collectAssets');
});

it('PageBlockRenderer::renderLayoutBlock has no $widgetAssets parameter', function () {
    $src = file_get_contents(app_path('Services/PageBlockRenderer.php'));
    expect($src)->not->toContain('$widgetAssets')
        ->and($src)->not->toContain('collectAssets');
});

it('ChromeRenderer no longer threads a widget-asset accumulator', function () {
    $src = file_get_contents(app_path('Services/Media/ChromeRenderer.php'));
    expect($src)->not->toContain('$widgetAssets')
        ->and($src)->not->toContain('collectAssets');
});

it('PageController does not initialise or pass a $widgetAssets local', function () {
    $src = file_get_contents(app_path('Http/Controllers/PageController.php'));
    expect($src)->not->toContain('widgetAssets')
        ->and($src)->not->toContain('collectAssets');
});

it('PostController does not initialise or pass a $widgetAssets local', function () {
    $src = file_get_contents(app_path('Http/Controllers/PostController.php'));
    expect($src)->not->toContain('widgetAssets')
        ->and($src)->not->toContain('collectAssets');
});
