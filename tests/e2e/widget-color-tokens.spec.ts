// Session 300 — Widget Colour-Token Consumption: real-browser, computed-value
// regression artifact across the three surfaces the Re-Taxonomy arc mandates.
// The 295/297 hard rule: never assert "it works" from emitted CSS or curl —
// only from getComputedStyle in a real browser.
//
//   1. ADMIN   (no .np-site)       — the tokens do not leak into Filament.
//   2. PREVIEW (.np-site present)  — the page-builder preview resolves the
//      --np-color-* token (the reason .np-site exists).
//
// The PUBLIC computed-token surface and the stronger "follows a live
// theme_colors override end-to-end" proof both exercise the external widget
// build pipeline (public/build/widgets/). The isolated e2e stack
// deliberately does NOT build it (no build server in CI, by recorded
// design), so neither is embedded here — both remain documented dev-box /
// manual checks (verified by computed values at session 300, recorded in
// the session log). What stays in CI is the build-pipeline-independent
// ADMIN no-leak and PREVIEW resolution coverage.

import { test, expect } from '@playwright/test';
import { findPageIdBySlug } from './helpers/db.js';

// The chromium project loads an authenticated storageState (captured by
// global-setup), so every test starts already logged into /admin — no
// per-test login (calling it would hit an already-authed redirect).

// --np-color-text-muted default = #6b7280.
const MUTED_DEFAULT = 'rgb(107, 114, 128)';

test.describe.configure({ mode: 'serial' });

test.describe('Widget colour-token consumption — three surfaces', () => {
    // PUBLIC computed-token test removed during e2e stabilization: it asserts
    // `.widget-blog-listing__empty` colour from the widget bundle's
    // --np-color-* token CSS, which only exists if `build:public` produced
    // public/build/widgets/ — the isolated e2e stack deliberately doesn't
    // build it (no build server in CI). Covered as a documented dev/manual
    // check; the ADMIN no-leak and PREVIEW tests below are CI-honest.

    test('ADMIN: --np-color-* tokens do not leak into Filament', async ({ page }) => {
        await page.goto('/admin');
        await page.locator('body').waitFor({ state: 'visible' });

        const brandOnAdminBody = await page.evaluate(() =>
            getComputedStyle(document.body).getPropertyValue('--np-color-brand').trim(),
        );
        expect(brandOnAdminBody).toBe('');
        await expect(page.locator('body.np-site')).toHaveCount(0);
    });

    test('PREVIEW: page-builder preview resolves the token faithfully', async ({ page }) => {
        const newsId = await findPageIdBySlug('news');
        expect(newsId).not.toBeNull();

        await page.goto(`/admin/pages/${newsId}/edit`);
        const previewEmpty = page.locator('.np-site .widget-blog-listing__empty').first();
        await expect(previewEmpty).toBeVisible({ timeout: 20_000 });
        // Faithful to PUBLIC — same token, same computed value.
        await expect(previewEmpty).toHaveCSS('color', MUTED_DEFAULT);
    });
});
