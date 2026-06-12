<?php

use App\Services\WidgetRegistry;
use App\Widgets\Contracts\WidgetDefinition;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Widgets allowed to ship a blank static.png. These are dev/admin tools with no
 * standalone preview — a rendered thumbnail would be empty by design. Pinned
 * like the demoSeeder list in WidgetManifestTest: adding or removing an entry
 * is a reviewed edit, never a way to clear the bar for a widget that should
 * actually render content. One-line reason per entry.
 */
const THUMBNAIL_BLANK_ALLOWLIST = [
    'random_data_generator', // dev tool — generates demo data, no standalone preview
    'setup_checklist',       // admin onboarding tool — no standalone preview
];

function widgetThumbnailFolder(WidgetDefinition $def): string
{
    return Str::replaceLast('Definition', '', class_basename($def));
}

it('every widget ships a non-blank static thumbnail or is allowlisted', function () {
    // The deterministic 800×500 empty-canvas capture. A committed static.png
    // byte-identical to this rendered nothing. A real render — however sparse —
    // diverges from it, so this fingerprint never false-positives on a valid
    // widget. Regenerate the fixture in the same reviewed pass if the blank
    // capture ever changes (e.g. a viewport or browser-render change).
    $blankHash = hash_file('sha256', base_path('tests/Fixtures/blank-widget-thumbnail.png'));

    $registry = app(WidgetRegistry::class);
    $handles  = array_map(fn (WidgetDefinition $def) => $def->handle(), $registry->all());

    // The allowlist may not carry a stale handle for a widget that no longer exists.
    foreach (THUMBNAIL_BLANK_ALLOWLIST as $allowed) {
        expect(in_array($allowed, $handles, true))->toBeTrue(
            "Thumbnail allowlist references unknown widget [{$allowed}] — remove the stale entry."
        );
    }

    foreach ($registry->all() as $def) {
        $handle = $def->handle();

        if (in_array($handle, THUMBNAIL_BLANK_ALLOWLIST, true)) {
            continue;
        }

        $path = base_path('app/Widgets/' . widgetThumbnailFolder($def) . '/thumbnails/static.png');

        expect(file_exists($path))->toBeTrue(
            "Widget [{$handle}] has no committed thumbnails/static.png."
        );

        expect(hash_file('sha256', $path))->not->toBe(
            $blankHash,
            "Widget [{$handle}] ships a blank static.png (matches the empty-canvas fingerprint). "
            . 'Give it demo data / a demoContext() / a manual thumbnail, or allowlist it with a reason.'
        );
    }
})->group('widget-lint');
