import { test, expect } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import {
    findPageIdBySlug,
    createWidgetOnPage,
    setWidgetConfig,
    getWidgetConfig,
    deleteWidget,
} from '../helpers/db.js';

// Session 306 — inline formatting toolbar (docs/inline-formatting-toolbar-spec.md).
// Session 347 — re-established as a STANDING spec and expanded to cover the
// extraction seams of the carve-out: the orchestrator (InlineFormatToolbar.vue)
// is split into the useInlineToolbarPosition + useInlineLinkPopover composables
// plus presentational popover sub-components. Pest does not exercise Vue, so
// this is the behaviour net for that refactor — it must stay green byte-for-byte
// in effect across every extraction step. Each case names the spec rule it pins.
//
// (The original 306 spec was a `describe.skip` placeholder deleted by the A005
// Playwright-discipline pass; the broader e2e suite is now un-parked, so this
// lands as a live, committed regression artifact.)
//
// Visibility note: the toolbar bar is always present in the DOM (it lives at the
// body root via <Teleport> and is shown/hidden by opacity + pointer-events, not
// v-if), so Playwright's toBeVisible/toBeHidden — which ignore opacity — can't
// read its shown state. Assertions key off the `opacity` CSS the lifecycle
// drives. The popovers ARE v-if'd, so toBeVisible/toBeHidden are correct there.

test.describe.configure({ mode: 'serial' });

// The Filament admin shell has a sticky top bar; centre the target in the
// viewport so clicks land clear of it (mirrors the 304 inline-editing specs).
async function centre(locator: Locator): Promise<void> {
    await locator.scrollIntoViewIfNeeded();
    await locator.evaluate((el) => el.scrollIntoView({ block: 'center', inline: 'center' }));
}

const BAR = '[data-inline-toolbar]';

// Select the widget, activate its richtext `content` node (mounts Quill), and
// wait for the floating toolbar to fade in (opacity → 1). Returns the editor +
// bar locators.
async function activate(page: Page, widgetId: string): Promise<{ editor: Locator; bar: Locator }> {
    const region = page.locator(`.preview-region[data-widget-id="${widgetId}"]`);
    await expect(region).toBeVisible({ timeout: 15_000 });
    await centre(region);
    await region.locator('.preview-region__edit').click();
    await expect(region).toHaveClass(/preview-region--selected/);

    const field = region.locator('[data-config-key="content"]');
    await field.click();
    const editor = field.locator('.ql-editor');
    await expect(editor).toBeVisible({ timeout: 15_000 });

    const bar = page.locator(BAR);
    await expect(bar).toHaveCSS('opacity', '1');
    return { editor, bar };
}

test.describe('Page builder — inline formatting toolbar', () => {
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

    test('appears positioned on activation and dismisses on outside click (§B1/§B4/§C — position composable)', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const { bar } = await activate(page, textBlockId);

        // Positioned on-screen near the field (updatePosition computed an
        // on-screen anchor → onScreen=true → opacity:1 above).
        const box = await bar.boundingBox();
        expect(box).not.toBeNull();
        const vp = page.viewportSize()!;
        expect(box!.y).toBeGreaterThanOrEqual(0);
        expect(box!.y).toBeLessThan(vp.height);
        expect(box!.x).toBeGreaterThanOrEqual(0);

        // Pointer-down outside both editor and toolbar ends the session → the
        // bar fades back to opacity:0 (§B4).
        await page.locator('body').click({ position: { x: 5, y: 5 } });
        await expect(bar).toHaveCSS('opacity', '0');
    });

    test('corner affordances are suppressed while inline-editing (§C14)', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        await activate(page, textBlockId);

        // Move the pointer off the region — the hover rule (higher specificity)
        // would otherwise re-reveal the chrome; the §C14 suppression is what
        // applies in the resting inline-active state.
        await page.mouse.move(5, 5);

        const region = page.locator(`.preview-region[data-widget-id="${textBlockId}"]`);
        await expect(region).toHaveClass(/preview-region--inline-active/);
        await expect(region.locator('.preview-region__handle')).toHaveCSS('opacity', '0');
        await expect(region.locator('.preview-region__edit')).toHaveCSS('opacity', '0');
    });

    test('bold toggle persists to saved config (§F2.1/§L — format dispatch)', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const { editor, bar } = await activate(page, textBlockId);

        await editor.click();
        await page.keyboard.press('ControlOrMeta+A');
        await bar.locator('button[aria-label="Bold"]').click();

        // Outside-click commits the raw HTML through the debounced save.
        await page.locator('body').click({ position: { x: 5, y: 5 } });
        await expect
            .poll(async () => String((await getWidgetConfig(textBlockId)).content ?? ''), { timeout: 15_000 })
            .toMatch(/<strong>|<b>/i);
    });

    test('heading applied via the text-style menu persists (§F1 — text-style sub-component)', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const { editor, bar } = await activate(page, textBlockId);

        await editor.click();
        await page.keyboard.press('ControlOrMeta+A');

        await bar.locator('.ift-textstyle').click();
        const menu = page.locator('.ift-textstyle-menu');
        await expect(menu).toBeVisible();
        await menu.getByRole('menuitem', { name: 'Heading 2' }).click();
        await expect(menu).toBeHidden();

        await page.locator('body').click({ position: { x: 5, y: 5 } });
        await expect
            .poll(async () => String((await getWidgetConfig(textBlockId)).content ?? ''), { timeout: 15_000 })
            .toMatch(/<h2/i);
    });

    test('color popover opens, renders the picker panel, and toggles closed (§H — color sub-component)', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const { editor, bar } = await activate(page, textBlockId);

        await editor.click();
        await page.keyboard.press('ControlOrMeta+A');

        const colorBtn = bar.locator('button[aria-label="Text color"]');
        await colorBtn.click();
        const popover = page.locator('.ift-color-popover');
        await expect(popover).toBeVisible();
        await expect(popover.locator('.color-picker__popover')).toBeVisible();
        await expect(popover.locator('[data-cp-swatch="1"]').first()).toBeVisible();

        // Re-clicking the trigger toggles it closed (openPopover → null).
        await colorBtn.click();
        await expect(popover).toBeHidden();
    });

    test('link popover saves href + target + rel with "open in new tab" (§G / §6.2 — link composable)', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const { editor, bar } = await activate(page, textBlockId);

        await editor.click();
        await page.keyboard.press('ControlOrMeta+A');

        await bar.locator('button[aria-label="Insert link"]').click();
        const popover = page.locator('.ift-link-popover');
        await expect(popover).toBeVisible();

        await popover.locator('input[placeholder="https://example.com"]').fill('https://example.com');
        await popover.locator('input[type="checkbox"]').check();
        await popover.locator('button:has-text("Save")').click();
        await expect(popover).toBeHidden();

        await page.locator('body').click({ position: { x: 5, y: 5 } });
        await expect
            .poll(async () => String((await getWidgetConfig(textBlockId)).content ?? ''), { timeout: 15_000 })
            .toContain('href="https://example.com"');

        const html = String((await getWidgetConfig(textBlockId)).content ?? '');
        expect(html).toContain('target="_blank"');
        expect(html).toMatch(/rel="[^"]*noopener[^"]*"/);
    });

    test('Cmd/Ctrl+K from inside the editor opens the link popover (§F4.2 — keyboard composable)', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const { editor } = await activate(page, textBlockId);

        // The shortcut fires only for keystrokes originating inside the active
        // editor (the §K window listener gate); click into the editor first.
        await editor.click();
        await page.keyboard.press('ControlOrMeta+k');
        await expect(page.locator('.ift-link-popover')).toBeVisible({ timeout: 5_000 });
    });
});
