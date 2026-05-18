import { test, expect } from '@playwright/test';
import type { Page, Locator } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import {
    findPageIdBySlug,
    createWidgetOnPage,
    setWidgetConfig,
    deleteWidget,
} from '../helpers/db.js';

// Session 304 (Page Builder Inline Editing — Session A, Phase 1):
// the interaction-model rework. Whole-panel selection is replaced by a
// top-left hover-in drag grip + a top-right hover-in "Edit" affordance;
// body-click still selects; the overlay is no longer a vuedraggable handle.

test.describe.configure({ mode: 'serial' });

const EDITOR_READY = '.preview-region[data-widget-id]';

async function rootWidgetOrder(page: Page): Promise<string[]> {
    return page.$$eval('.preview-region[data-widget-id]', (els) =>
        els.map((el) => el.getAttribute('data-widget-id') ?? ''),
    );
}

// The Filament admin shell has a sticky top bar (z-20) that overlaps the top
// of any element merely scrolled "into view". Centre the region in the
// viewport so clicks/drags land clear of it.
async function centre(locator: Locator): Promise<void> {
    await locator.scrollIntoViewIfNeeded();
    await locator.evaluate((el) =>
        el.scrollIntoView({ block: 'center', inline: 'center' }),
    );
}

const TALL = (label: string) =>
    `<p>${label}</p><p>line two</p><p>line three</p><p>line four</p>`;

// Drive SortableJS through native pointer steps: mousedown on the source,
// a small nudge to arm the drag, a stepped move past the target's lower
// edge (swap-threshold 0.65), then release.
async function dragFromTo(page: Page, source: Locator, target: Locator): Promise<void> {
    const s = await source.boundingBox();
    const t = await target.boundingBox();
    if (!s || !t) throw new Error('drag source/target not laid out');

    await page.mouse.move(s.x + s.width / 2, s.y + s.height / 2);
    await page.mouse.down();
    await page.mouse.move(s.x + s.width / 2, s.y + s.height / 2 + 8, { steps: 6 });
    await page.mouse.move(t.x + t.width / 2, t.y + t.height + 24, { steps: 18 });
    await page.mouse.move(t.x + t.width / 2, t.y + t.height + 26, { steps: 4 });
    await page.mouse.up();
}

test.describe('Page builder — interaction model (session 304 Phase 1)', () => {
    let pageId: string;
    let widgetA: string;
    let widgetB: string;

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        const id = await findPageIdBySlug('home');
        if (!id) throw new Error('home page not found');
        pageId = id;
        // A created before B → A precedes B in the initial root flow.
        // Real content gives each region intrinsic height (an empty
        // text_block renders nothing and is zero-size in the canvas).
        widgetA = await createWidgetOnPage(pageId, 'text_block');
        widgetB = await createWidgetOnPage(pageId, 'text_block');
        await setWidgetConfig(widgetA, { content: TALL('Widget Alpha — Phase 1 grip.') });
        await setWidgetConfig(widgetB, { content: TALL('Widget Bravo — Phase 1 target.') });
    });

    test.afterAll(async () => {
        if (widgetA) await deleteWidget(widgetA);
        if (widgetB) await deleteWidget(widgetB);
    });

    test('body-click selects the widget (no whole-panel button needed)', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const regionA = page.locator(`.preview-region[data-widget-id="${widgetA}"]`);
        const regionB = page.locator(`.preview-region[data-widget-id="${widgetB}"]`);
        await expect(regionB).toBeVisible({ timeout: 15_000 });

        // Body-click B → B selected, A not. Then body-click A → flips.
        await centre(regionB);
        await regionB.locator('.preview-region__overlay').click();
        await expect(regionB).toHaveClass(/preview-region--selected/);
        await expect(regionA).not.toHaveClass(/preview-region--selected/);
        await expect(page.locator('.inspector-header')).toBeVisible();

        await centre(regionA);
        await regionA.locator('.preview-region__overlay').click();
        await expect(regionA).toHaveClass(/preview-region--selected/);
        await expect(regionB).not.toHaveClass(/preview-region--selected/);
    });

    test('the top-right Edit affordance opens the Inspector on its content tab', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const regionA = page.locator(`.preview-region[data-widget-id="${widgetA}"]`);
        const regionB = page.locator(`.preview-region[data-widget-id="${widgetB}"]`);
        await expect(regionB).toBeVisible({ timeout: 15_000 });

        // Select B and move its inspector away from the Content tab.
        await centre(regionB);
        await regionB.locator('.preview-region__overlay').click();
        await expect(regionB).toHaveClass(/preview-region--selected/);
        await page.locator('.inspector-pane--top .inspector-tabs__btn', { hasText: 'Widget Settings' }).click();
        await expect(
            page.locator('.inspector-pane--top .inspector-tabs__btn--active'),
        ).toHaveText('Widget Settings');

        // The Edit affordance on A: selects A AND returns the Inspector to Content.
        await centre(regionA);
        const editA = regionA.locator('.preview-region__edit');
        await regionA.hover();
        await editA.click();

        await expect(regionA).toHaveClass(/preview-region--selected/);
        await expect(regionB).not.toHaveClass(/preview-region--selected/);
        await expect(
            page.locator('.inspector-pane--top .inspector-tabs__btn--active'),
        ).toHaveText('Content');
    });

    test('the overlay body is no longer a drag handle (whole-panel reorder gone)', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        await expect(page.locator(EDITOR_READY).first()).toBeVisible({ timeout: 15_000 });

        const before = await rootWidgetOrder(page);
        const regionA = page.locator(`.preview-region[data-widget-id="${widgetA}"]`);
        const regionB = page.locator(`.preview-region[data-widget-id="${widgetB}"]`);

        // Drag starting on the body overlay must NOT reorder anything.
        await centre(regionA);
        await dragFromTo(page, regionA.locator('.preview-region__overlay'), regionB);
        await page.waitForTimeout(1200);

        expect(await rootWidgetOrder(page)).toEqual(before);
    });

    test('the top-left grip reorders the widget and the new order persists', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const regionA = page.locator(`.preview-region[data-widget-id="${widgetA}"]`);
        const regionB = page.locator(`.preview-region[data-widget-id="${widgetB}"]`);
        await expect(regionA).toBeVisible({ timeout: 15_000 });

        const before = await rootWidgetOrder(page);
        expect(before.indexOf(widgetA)).toBeLessThan(before.indexOf(widgetB));

        // The grip is hover-revealed; centre + hover, then drag from it.
        await centre(regionA);
        await regionA.hover();
        await dragFromTo(page, regionA.locator('.preview-region__handle'), regionB);
        await page.waitForTimeout(1500);

        // Reload — order is authoritative from the persisted tree.
        await page.reload();
        await expect(page.locator(EDITOR_READY).first()).toBeVisible({ timeout: 15_000 });
        const after = await rootWidgetOrder(page);
        expect(after.indexOf(widgetA)).toBeGreaterThan(after.indexOf(widgetB));
    });
});
