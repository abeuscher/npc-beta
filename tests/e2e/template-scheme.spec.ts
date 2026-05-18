// Session 301 — Theme/Template Re-Taxonomy 3: per-template content scheme,
// real-browser computed-value verification across the three surfaces the arc
// mandates. The 295/297/300 hard rule: never assert "it works" from emitted
// CSS or curl — only from getComputedStyle in a real browser.
//
//   1. PUBLIC  — the content region (<main>) follows the template's selected
//      scheme; the standard chrome keeps its Theme colour (compose-not-bleed).
//   2. ADMIN   — the --np-color-* tokens still do not leak into Filament.
//   3. PREVIEW — the page-builder preview content scope resolves the SAME
//      scheme override, byte-identical to public (one shared resolver, the
//      reason .np-site exists).
//
// Scheme overrides are request-time inline (never the bundle), so flipping a
// template's scheme needs NO rebuild — this is a fast, dependency-free
// artifact (unlike the bundle-compiled theme_colors override of session 300).
//
// Runs on the seeded data via the isolated e2e stack
// (npm run test:e2e:isolated): the `news` page uses the default page
// template; flipping that template's scheme drives the public content region
// and the page-builder preview together.

import { test, expect } from '@playwright/test';
import {
    findPageIdBySlug,
    setDefaultPageTemplateScheme,
    getDefaultPageTemplateScheme,
} from './helpers/db.js';

// The Inverse scheme's content-region background (TemplateAppearanceResolver).
const INVERSE_BG = '#111827';
const INVERSE_TEXT = '#f3f4f6';
// …as the browser reports the *rendered* paint (proves it's visible, not
// just that the custom property is set — the 295/297 computed-value rule).
const INVERSE_BG_RGB = 'rgb(17, 24, 39)';
const INVERSE_TEXT_RGB = 'rgb(243, 244, 246)';

function cssVar(selector: string, prop: string) {
    return async ({ page }: { page: import('@playwright/test').Page }) =>
        page.evaluate(
            ([sel, p]) => {
                const el = document.querySelector(sel);
                return el ? getComputedStyle(el).getPropertyValue(p).trim() : null;
            },
            [selector, prop] as const,
        );
}

test.describe.configure({ mode: 'serial' });

test.describe('Per-template content scheme — three surfaces, scheme-switched', () => {
    let originalScheme = 'default';

    test.beforeAll(async () => {
        originalScheme = await getDefaultPageTemplateScheme();
    });

    test.afterAll(async () => {
        await setDefaultPageTemplateScheme(originalScheme);
    });

    test('PUBLIC default scheme = identity (content region == the Theme .np-site value)', async ({ page }) => {
        await setDefaultPageTemplateScheme('default');
        await page.goto('/news');
        await expect(page.locator('body.np-site')).toHaveCount(1);

        const bodyBg = await cssVar('body', '--np-color-bg')({ page });
        const mainBg = await cssVar('main', '--np-color-bg')({ page });

        // Default = empty delta: <main> carries no override → it inherits the
        // bundle's .np-site token exactly (byte-identical to today).
        expect(mainBg).toBe(bodyBg);
        expect(mainBg).not.toBe(INVERSE_BG);
    });

    test('PUBLIC inverse scheme: content region follows, chrome holds (compose-not-bleed)', async ({ page }) => {
        await setDefaultPageTemplateScheme('inverse');
        await page.goto('/news');
        await expect(page.locator('body.np-site')).toHaveCount(1);

        const bodyBg = await cssVar('body', '--np-color-bg')({ page });
        const mainBg = await cssVar('main', '--np-color-bg')({ page });
        const mainText = await cssVar('main', '--np-color-text')({ page });
        const headerBg = await cssVar('.site-nav-wrapper', '--np-color-bg')({ page });

        // Content region follows the selected scheme…
        expect(mainBg).toBe(INVERSE_BG);
        expect(mainText).toBe(INVERSE_TEXT);
        // …and it is actually PAINTED (visible), not just the token set.
        await expect(page.locator('main')).toHaveCSS('background-color', INVERSE_BG_RGB);
        await expect(page.locator('main')).toHaveCSS('color', INVERSE_TEXT_RGB);
        // …while the standard chrome keeps the Theme value (it did not bleed).
        expect(headerBg).toBe(bodyBg);
        expect(headerBg).not.toBe(INVERSE_BG);
        await expect(page.locator('.site-nav-wrapper')).not.toHaveCSS('background-color', INVERSE_BG_RGB);
    });

    test('ADMIN: --np-color-* tokens still do not leak into Filament', async ({ page }) => {
        await page.goto('/admin');
        await page.locator('body').waitFor({ state: 'visible' });

        const bgOnAdminBody = await page.evaluate(() =>
            getComputedStyle(document.body).getPropertyValue('--np-color-bg').trim(),
        );
        expect(bgOnAdminBody).toBe('');
        await expect(page.locator('body.np-site')).toHaveCount(0);
    });

    test('PREVIEW: page-builder preview content scope follows the SAME scheme, faithful to public', async ({ page }) => {
        // scheme is still 'inverse' from the public test above.
        const newsId = await findPageIdBySlug('news');
        expect(newsId).not.toBeNull();

        await page.goto(`/admin/pages/${newsId}/edit`);
        const scope = page.locator('.widget-preview-scope.np-site').first();
        await expect(scope).toBeVisible({ timeout: 20_000 });

        const scopeBg = await cssVar('.widget-preview-scope.np-site', '--np-color-bg')({ page });
        const scopeText = await cssVar('.widget-preview-scope.np-site', '--np-color-text')({ page });

        // Identical to PUBLIC's <main> — one shared resolver, no divergence.
        expect(scopeBg).toBe(INVERSE_BG);
        expect(scopeText).toBe(INVERSE_TEXT);
        await expect(scope).toHaveCSS('background-color', INVERSE_BG_RGB);
        await expect(scope).toHaveCSS('color', INVERSE_TEXT_RGB);
    });

    test('PREVIEW reverts with the scheme: default → back to the Theme value', async ({ page }) => {
        await setDefaultPageTemplateScheme('default');
        const newsId = await findPageIdBySlug('news');

        await page.goto(`/admin/pages/${newsId}/edit`);
        const scope = page.locator('.widget-preview-scope.np-site').first();
        await expect(scope).toBeVisible({ timeout: 20_000 });

        const scopeBg = await cssVar('.widget-preview-scope.np-site', '--np-color-bg')({ page });
        // No override emitted → the preview scope falls back to the bundle's
        // .np-site token (not the inverse value): identity restored.
        expect(scopeBg).not.toBe(INVERSE_BG);
    });
});
