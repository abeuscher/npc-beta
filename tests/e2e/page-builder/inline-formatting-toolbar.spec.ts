import { test, expect } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import {
    findPageIdBySlug,
    createWidgetOnPage,
    setWidgetConfig,
    getWidgetConfig,
    deleteWidget,
} from '../helpers/db.js';

// Session 306 — Inline formatting toolbar (docs/inline-formatting-toolbar-spec.md).
// PARKED until the parallel e2e-stabilisation effort lands and the playwright
// job is un-parked. The spec rules covered here are independently testable per
// `§5`, but the suite as a whole rides on the same cold-SCSS-render cause that
// quarantined the broader page-builder e2e set. Re-enable after the e2e stack
// stabilises (see sessions/playwright-test-fix.md / memory/project_e2e_suite_systemic_scss_cause).

test.describe.configure({ mode: 'serial' });

test.describe.skip('Page builder — inline formatting toolbar', () => {
    let pageId: string;
    let textBlockId: string;

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        const id = await findPageIdBySlug('home');
        if (!id) throw new Error('home page not found');
        pageId = id;

        textBlockId = await createWidgetOnPage(pageId, 'text_block');
        await setWidgetConfig(textBlockId, { content: '<p>Hello world from session 306.</p>' });
    });

    test.afterAll(async () => {
        if (textBlockId) await deleteWidget(textBlockId);
    });

    test('toolbar appears on richtext activation and disappears on outside click (§B1/§B4)', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const region = page.locator(`.preview-region[data-widget-id="${textBlockId}"]`);
        await expect(region).toBeVisible({ timeout: 15_000 });

        // Select the widget
        await region.locator('.preview-region__edit').click();
        await expect(region).toHaveClass(/preview-region--selected/);

        // Activate inline edit on the richtext field
        const field = region.locator('[data-config-key="content"]');
        await field.click();

        // Toolbar mounts at body root
        const bar = page.locator('[data-inline-toolbar]');
        await expect(bar).toBeVisible({ timeout: 5_000 });

        // Pointer-down outside both editor and toolbar dismisses (§B4)
        await page.locator('body').click({ position: { x: 5, y: 5 } });
        await expect(bar).toBeHidden({ timeout: 2_000 });
    });

    test('bold toggle persists to saved config (§F2.1 / §L)', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const region = page.locator(`.preview-region[data-widget-id="${textBlockId}"]`);
        await region.locator('.preview-region__edit').click();

        const field = region.locator('[data-config-key="content"]');
        await field.click();

        // Select all text in the editor
        await page.keyboard.press('Control+A');

        const bar = page.locator('[data-inline-toolbar]');
        await bar.locator('button[aria-label="Bold"]').click();

        // Click outside to commit
        await page.locator('body').click({ position: { x: 5, y: 5 } });

        // Wait for debounced save + verify config
        await page.waitForTimeout(800);
        const config = await getWidgetConfig(textBlockId);
        expect(String(config.content)).toMatch(/<strong>|<b>/i);
    });

    test('corner affordances hidden while inline-editing (§C14)', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const region = page.locator(`.preview-region[data-widget-id="${textBlockId}"]`);
        await region.locator('.preview-region__edit').click();

        const field = region.locator('[data-config-key="content"]');
        await field.click();
        await expect(page.locator('[data-inline-toolbar]')).toBeVisible({ timeout: 5_000 });

        // Both corner affordances should be visually suppressed
        await expect(region).toHaveClass(/preview-region--inline-active/);
        const handle = region.locator('.preview-region__handle');
        const edit = region.locator('.preview-region__edit');
        await expect(handle).toHaveCSS('opacity', '0');
        await expect(edit).toHaveCSS('opacity', '0');
    });

    test('link popover saves target+rel when "open in new tab" is checked (§G / §6.2)', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const region = page.locator(`.preview-region[data-widget-id="${textBlockId}"]`);
        await region.locator('.preview-region__edit').click();

        const field = region.locator('[data-config-key="content"]');
        await field.click();
        await page.keyboard.press('Control+A');

        const bar = page.locator('[data-inline-toolbar]');
        await bar.locator('button[aria-label="Insert link"]').click();

        const popover = page.locator('.ift-link-popover');
        await expect(popover).toBeVisible();

        await popover.locator('input[type="text"]').first().fill('https://example.com');
        await popover.locator('input[type="checkbox"]').check();
        await popover.locator('button:has-text("Save")').click();

        await page.locator('body').click({ position: { x: 5, y: 5 } });
        await page.waitForTimeout(800);

        const config = await getWidgetConfig(textBlockId);
        const html = String(config.content);
        expect(html).toContain('href="https://example.com"');
        expect(html).toContain('target="_blank"');
        expect(html).toMatch(/rel="[^"]*noopener[^"]*"/);
    });
});
