// Session 317 — E19 Accessibility pass. Standing accessibility regression guard.
//
// Runs axe-core against the public-render surface — the markup this project
// owns end-to-end, and where the demo's accessibility is visible to prospects.
// It catches the class of regressions the Aria sweep fixed: a future widget
// shipping an unlabelled icon button, a missing alt, a broken landmark, an
// unnamed link.
//
// Scope decisions:
//  - `color-contrast` is disabled — visual-contrast accessibility is out of
//    scope for the Aria sweep and pairs with the Design System Editor track
//    (per the E19 out-of-scope list).
//  - The admin Filament shell is intentionally NOT scanned. Its axe violations
//    are stock-framework markup this project does not author (modal dialog
//    names, positive tabindex on the login form), which would make this guard
//    flaky rather than catch our own regressions.
//
// Runs on the seeded data via the isolated e2e stack (npm run test:e2e).

import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const PUBLIC_URLS = ['/', '/news'];

for (const url of PUBLIC_URLS) {
    test(`a11y: ${url} has no WCAG A/AA axe violations (excl. color-contrast)`, async ({ page }) => {
        await page.goto(url);
        await page.locator('body.np-site').waitFor({ state: 'visible' });

        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
            .disableRules(['color-contrast'])
            .analyze();

        // On failure, name each violation + node count so the report is
        // actionable without re-running with a debugger.
        const summary = results.violations
            .map((v) => `${v.id} (${v.impact}): ${v.nodes.length} node(s) — ${v.help}`)
            .join('\n');

        expect(results.violations, summary).toEqual([]);
    });
}
