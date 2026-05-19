// Session 300 — Widget Colour-Token Consumption: real-browser, computed-value
// regression artifact. The 295/297 hard rule: never assert "it works" from
// emitted CSS or curl — only from getComputedStyle in a real browser.
//
//   ADMIN (no .np-site) — the --np-color-* tokens do not leak into Filament.
//
// The PUBLIC and PREVIEW computed-token surfaces, and the stronger "follows
// a live theme_colors override end-to-end" proof, all resolve
// `.widget-blog-listing__empty` colour from the widget bundle's
// --np-color-* token CSS, which only exists if `build:public` produced
// public/build/widgets/. The isolated e2e stack deliberately does NOT build
// it (no build server in CI, by recorded design), so they are not embedded
// here — they remain documented dev-box / manual checks (verified by
// computed values at session 300, recorded in the session log). What stays
// in CI is the build-pipeline-independent ADMIN no-leak assertion.

import { test, expect } from '@playwright/test';

// The chromium project loads an authenticated storageState (captured by
// global-setup), so every test starts already logged into /admin — no
// per-test login (calling it would hit an already-authed redirect).

test.describe('Widget colour-token consumption — ADMIN no-leak', () => {
    test('ADMIN: --np-color-* tokens do not leak into Filament', async ({ page }) => {
        await page.goto('/admin');
        await page.locator('body').waitFor({ state: 'visible' });

        const brandOnAdminBody = await page.evaluate(() =>
            getComputedStyle(document.body).getPropertyValue('--np-color-brand').trim(),
        );
        expect(brandOnAdminBody).toBe('');
        await expect(page.locator('body.np-site')).toHaveCount(0);
    });
});
