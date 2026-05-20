<?php

// Rot guard for the session-298 scoped inner loop: `./dev test:design`
// (php artisan test --group=design) is the ~30-second scoped signal for
// design/drift work, and it is only trustworthy if the `design` group
// contains exactly the reviewed cluster. This test pins membership to an
// explicit, reviewed list — NOT a naming/namespace heuristic, which the
// cluster's deliberately non-uniform naming would over- and under-match
// forever. It fails both ways: a file tagged ->group('design') that is not
// on the list (silent scope creep), and a listed file that lost its tag
// (silent coverage hole in the inner loop). Editing the cluster means
// editing this list in the same reviewed pass — that is the whole point.

use Tests\TestCase;

uses(TestCase::class);

// The reviewed design/drift cluster. Paths are relative to the repo root.
// Typography + colour/appearance + the build-pipeline / stale-bundle drift
// files (the 296 AssetBundleDriftGuard stays in scope on purpose so the
// scoped loop still catches the stale-stylesheet bug class).
const DESIGN_GROUP_FILES = [
    'tests/Unit/TypographyCompilerTest.php',
    'tests/Unit/TypographyResolverTest.php',
    'tests/Unit/AppearanceStyleComposerTest.php',
    'tests/Feature/TypographyMigrationTest.php',
    'tests/Feature/ThemeTypographyControllerTest.php',
    'tests/Feature/ThemeColorRelocationByteFaithfulTest.php',
    'tests/Feature/AssetBuildSession123Test.php',
    'tests/Feature/AssetBundleDriftGuardTest.php',
    'tests/Feature/BuildServerSettingsSession125Test.php',
    'tests/Feature/WidgetJsLibsSession138Test.php',
    'tests/Feature/DesignSystemButtonsTest.php',
    'tests/Feature/WidgetColorTokenConsumptionTest.php',
    'tests/Feature/TemplateAppearanceResolverTest.php',
    'tests/Feature/GradientComposerTest.php',
    'tests/Feature/PageBlockRendererLayoutTest.php',
    'tests/Feature/AppearanceBackgroundRenderTest.php',
    'tests/Feature/AppearanceConfigRenameTest.php',
    'tests/Feature/AppearanceImageUploadTest.php',
    'tests/Feature/AppearanceLayerRegressionTest.php',
    'tests/Feature/BuilderPublicRenderParityTest.php',
];

function discoverDesignTaggedFiles(): array
{
    $root = base_path();
    $found = [];

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('tests'), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        // The meta-guard is not a design-surface test; its own comment and
        // failure-message text necessarily mentions the tag, so it must
        // exclude itself from the scan.
        if ($file->getFilename() === 'DesignGroupIntegrityTest.php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        // Matches ->group('design'), ->group("design"), and multi-arg forms
        // such as ->group('design', 'slow') or ->group('slow', 'design').
        if (preg_match('/->group\([^)]*[\'"]design[\'"]/', $contents)) {
            $found[] = str_replace($root . '/', '', $file->getPathname());
        }
    }

    sort($found);

    return $found;
}

it('keeps the design group equal to the reviewed explicit list', function () {
    $expected = DESIGN_GROUP_FILES;
    sort($expected);

    $actual = discoverDesignTaggedFiles();

    $unexpected = array_values(array_diff($actual, $expected));
    $missing    = array_values(array_diff($expected, $actual));

    expect($unexpected)->toBe(
        [],
        'Files tagged ->group(\'design\') but not on the reviewed list — add them to '
        . 'DESIGN_GROUP_FILES in a reviewed pass, or drop the tag: '
        . implode(', ', $unexpected),
    );

    expect($missing)->toBe(
        [],
        'Reviewed design-group files that no longer carry the ->group(\'design\') tag '
        . '— the scoped inner loop is silently missing them: '
        . implode(', ', $missing),
    );

    expect($actual)->toBe($expected);
});

it('lists exactly the reviewed number of design-group files', function () {
    expect(DESIGN_GROUP_FILES)->toHaveCount(20);
    expect(DESIGN_GROUP_FILES)->toBe(array_unique(DESIGN_GROUP_FILES));
});
