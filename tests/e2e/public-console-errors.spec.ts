// Session 375 — the browser half of the CSP/Alpine regression net.
//
// The enforced public Content-Security-Policy (session 370) grants no
// 'unsafe-eval'. Standard Alpine evaluates every directive through
// `new Function()`, so from the S1 deploy until session 374 every public page
// threw EvalError at startup and no Alpine component initialized — nav
// dropdowns, custom selects and the theme store were all dead in production for
// four sessions. Nothing caught it, because nothing looked at the console.
//
// This spec is what would have caught it on day one, and it asserts both halves
// of "fixed": the pages emit nothing, AND the components actually work. A
// silently-broken expression under the CSP-safe build yields an inert widget,
// which a console-only check would still pass on a page that renders it dead.
//
// Its static counterpart is tests/Feature/PublicAlpineCspGuardTest.php, which
// needs no browser and therefore runs everywhere.
//
// BUNDLE DEPENDENCY: every widget component now lives in the public widget
// bundle (public/build/widgets/), which the isolated e2e stack deliberately
// does not build — there is no build server in CI, by recorded design (see the
// same note in widget-color-tokens.spec.ts). These tests skip themselves when
// the bundle is absent rather than reporting a failure that is really an
// environment gap. On a dev box that has run `build:public` they run in full.

import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';

type Collected = { kind: string; text: string };

function collectConsole(page: Page): Collected[] {
    const entries: Collected[] = [];

    page.on('console', (msg) => {
        if (['error', 'warning'].includes(msg.type())) {
            entries.push({ kind: `console.${msg.type()}`, text: msg.text() });
        }
    });
    page.on('pageerror', (err) => entries.push({ kind: 'pageerror', text: String(err) }));

    return entries;
}

/** The widget bundle carries every registered component; without it, skip. */
async function requireWidgetBundle(page: Page): Promise<void> {
    const present = await page.evaluate(() => typeof (window as any).NPWidgets === 'object');

    test.skip(!present, 'public widget bundle not built in this environment (no build server in CI)');
}

const PUBLIC_PAGES = ['/', '/about', '/contact', '/pricing', '/privacy-policy', '/terms-of-use'];

test.describe('Public pages emit no console errors under the enforced CSP', () => {
    for (const path of PUBLIC_PAGES) {
        test(`${path} is console-clean`, async ({ page }) => {
            const entries = collectConsole(page);

            const response = await page.goto(path, { waitUntil: 'networkidle' });
            test.skip(response !== null && response.status() === 404, `${path} does not exist in this environment`);

            await requireWidgetBundle(page);
            await page.waitForTimeout(500);

            expect(entries, entries.map((e) => `[${e.kind}] ${e.text}`).join('\n')).toEqual([]);
        });
    }
});

test.describe('Alpine components initialize and respond under the CSP-safe build', () => {
    test('the theme store is registered and toggles', async ({ page }) => {
        const entries = collectConsole(page);
        await page.goto('/', { waitUntil: 'networkidle' });
        await requireWidgetBundle(page);

        // A registered store proves Alpine started — the exact thing EvalError
        // prevented in production.
        const before = await page.evaluate(() => (window as any).Alpine.store('theme').current);
        await page.evaluate(() => (window as any).Alpine.store('theme').toggle());
        const after = await page.evaluate(() => (window as any).Alpine.store('theme').current);

        expect(after).not.toBe(before);
        expect(['light', 'dark']).toContain(after);
        expect(entries).toEqual([]);
    });

    test('nav dropdowns open on keyboard and close on escape', async ({ page }) => {
        const entries = collectConsole(page);
        await page.goto('/dev/widgets/nav', { waitUntil: 'networkidle' });
        await requireWidgetBundle(page);

        const trigger = page.locator('[aria-haspopup="true"]').first();
        test.skip(await trigger.count() === 0, 'demo nav has no dropdown items');

        const menu = page.locator('[role="menu"]').first();
        await expect(menu).toBeHidden();

        await trigger.locator('a, button').first().focus();
        await page.keyboard.press('ArrowDown');
        await expect(menu).toBeVisible();

        // Session-334 behaviour: focus lands on the first item in the menu.
        await expect(menu.locator('[role="menuitem"]').first()).toBeFocused();

        await page.keyboard.press('Escape');
        await expect(menu).toBeHidden();

        expect(entries, entries.map((e) => `[${e.kind}] ${e.text}`).join('\n')).toEqual([]);
    });

    test('the custom select opens, filters and selects', async ({ page }) => {
        const entries = collectConsole(page);
        await page.goto('/dev/widgets/donation_form', { waitUntil: 'networkidle' });
        await requireWidgetBundle(page);

        const select = page.locator('.custom-select').first();
        test.skip(await select.count() === 0, 'demo donation form renders no custom select');

        const panel = select.locator('.custom-select__dropdown');
        await expect(panel).toBeHidden();

        await select.locator('.custom-select__trigger').click();
        await expect(panel).toBeVisible();

        // Options come from the component's `filtered` getter — proof the
        // x-for and the getter both evaluate.
        const options = select.locator('[role="option"]');
        expect(await options.count()).toBeGreaterThan(0);

        const label = (await options.first().textContent())?.trim();
        await options.first().click();
        await expect(panel).toBeHidden();
        await expect(select.locator('.custom-select__trigger')).toContainText(label ?? '');

        expect(entries, entries.map((e) => `[${e.kind}] ${e.text}`).join('\n')).toEqual([]);
    });

    test('the donation form tracks amount selection', async ({ page }) => {
        const entries = collectConsole(page);
        await page.goto('/dev/widgets/donation_form', { waitUntil: 'networkidle' });
        await requireWidgetBundle(page);

        const preset = page.locator('.amount-grid button').first();
        test.skip(await preset.count() === 0, 'demo donation form renders no preset amounts');

        await preset.click();
        await expect(preset).toHaveAttribute('aria-pressed', 'true');

        // Custom amount reveals its input and clears the preset — both driven
        // by component methods rather than inline statements.
        const customButton = page.locator('button', { hasText: /custom/i }).first();
        if (await customButton.count()) {
            await customButton.click();
            await expect(preset).toHaveAttribute('aria-pressed', 'false');
        }

        expect(entries, entries.map((e) => `[${e.kind}] ${e.text}`).join('\n')).toEqual([]);
    });

    test('the social sharing copy button confirms', async ({ page, context }) => {
        await context.grantPermissions(['clipboard-read', 'clipboard-write']);

        const entries = collectConsole(page);
        await page.goto('/dev/widgets/social_sharing', { waitUntil: 'networkidle' });
        await requireWidgetBundle(page);

        const copy = page.locator('.social-sharing__copied').first();
        test.skip(await copy.count() === 0, 'demo social sharing renders no copy button');

        await expect(copy).toBeHidden();
        await page.locator('[x-data="socialSharingCopy"]').first().click();
        await expect(copy).toBeVisible();

        expect(entries, entries.map((e) => `[${e.kind}] ${e.text}`).join('\n')).toEqual([]);
    });
});
