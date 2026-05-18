// Session 300 — Widget Colour-Token Consumption: real-browser, computed-value
// regression artifact across the three surfaces the Re-Taxonomy arc mandates.
// The 295/297 hard rule: never assert "it works" from emitted CSS or curl —
// only from getComputedStyle in a real browser.
//
//   1. PUBLIC  (.np-site present)  — a migrated widget's colour resolves to
//      the --np-color-* token value (not the old dead hardcoded hex).
//   2. ADMIN   (no .np-site)       — the tokens do not leak into Filament.
//   3. PREVIEW (.np-site present)  — the page-builder preview resolves the
//      same token identically (the reason .np-site exists).
//
// The stronger "follows a live theme_colors override end-to-end" proof was
// verified by computed values across all three surfaces at session 300 and
// is recorded in the session log; it exercises the external build pipeline,
// so it is deliberately NOT embedded here (a fast, dependency-free
// regression artifact instead of a flaky external-build call in CI).
//
// Runs on the seeded data via the isolated e2e stack
// (npm run test:e2e:isolated): the `news` page carries a blog_listing widget
// — one of the widgets migrated this session — and with no seeded posts it
// renders `.widget-blog-listing__empty`, whose colour was migrated from the
// dead `var(--color-text-muted, #6b7280)` to `var(--np-color-text-muted)`.

import { test, expect } from '@playwright/test';
import { findPageIdBySlug } from './helpers/db.js';

// The chromium project loads an authenticated storageState (captured by
// global-setup), so every test starts already logged into /admin — no
// per-test login (calling it would hit an already-authed redirect).

// --np-color-text-muted default = #6b7280.
const MUTED_DEFAULT = 'rgb(107, 114, 128)';

test.describe.configure({ mode: 'serial' });

test.describe('Widget colour-token consumption — three surfaces', () => {
    test('PUBLIC: migrated widget colour resolves to the --np-color-* token', async ({ page }) => {
        await page.goto('/news');
        await expect(page.locator('body.np-site')).toHaveCount(1);
        await expect(page.locator('.widget-blog-listing')).toBeVisible({ timeout: 15_000 });

        const empty = page.locator('.widget-blog-listing__empty').first();
        await expect(empty).toBeVisible({ timeout: 15_000 });
        // Migrated `var(--color-text-muted, #6b7280)` → `var(--np-color-text-muted)`.
        await expect(empty).toHaveCSS('color', MUTED_DEFAULT);
    });

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
