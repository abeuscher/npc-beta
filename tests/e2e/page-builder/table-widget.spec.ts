// Session 349 — standing CI guard for the Table widget's embedded ProseMirror
// editor.
//
// Drives the real authoring round-trip: open a Table widget's editor in the
// page builder, insert a table from the grid picker, type cell content, add a
// row, toggle the header row, merge two cells, then assert the result persists
// (autosave) and survives to the public render as a real <table>. This is the
// one seam Pest can't reach — that the embedded editor actually mounts in the
// admin and its output round-trips. Cell-attribute sanitisation (colspan /
// rowspan / strip) is covered by Pest (HtmlSanitizerTest, TableDefinitionTest);
// here we only confirm a merge reaches the stored HTML as colspan.
//
// The widget is removed in afterAll so the row does not leak into the next
// manual-test session.

import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import {
    findPageIdBySlug,
    createWidgetOnPage,
    deleteWidget,
    getWidgetConfig,
    getPageSlug,
} from '../helpers/db.js';

test.describe.configure({ mode: 'serial' });

// Drag-select from one cell to another to form a prosemirror-tables
// CellSelection (the precondition for Merge).
async function dragSelect(page: Page, from: { x: number; y: number; width: number; height: number }, to: { x: number; y: number; width: number; height: number }): Promise<void> {
    await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2);
    await page.mouse.down();
    await page.mouse.move(to.x + to.width / 2, to.y + to.height / 2, { steps: 12 });
    await page.mouse.up();
}

test.describe('Table widget — embedded ProseMirror authoring round-trip', () => {
    let pageId: string;
    let widgetId: string;

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        const id = await findPageIdBySlug('home');
        if (!id) throw new Error('home page not found');
        pageId = id;
        widgetId = await createWidgetOnPage(pageId, 'table');
    });

    test.afterAll(async () => {
        if (widgetId) await deleteWidget(widgetId);
    });

    test('build a table in the editor and see it on the public page', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);

        // Select the table widget.
        const region = page.locator(`.preview-region[data-widget-id="${widgetId}"]`);
        await expect(region).toBeVisible({ timeout: 15_000 });
        await region.scrollIntoViewIfNeeded();
        await region.locator('.preview-region__overlay').click();
        await expect(page.locator('.inspector-header')).toBeVisible();

        // Open the embedded editor and insert a 3×3 table (header row on).
        await page.locator('.table-field__open').click();
        const modal = page.locator('.table-editor-modal');
        await expect(modal).toBeVisible();
        await modal.locator('.table-grid-picker__cell[aria-label="Insert 3 by 3 table"]').click();

        const pm = modal.locator('.ProseMirror');
        await expect(pm.locator('table')).toBeVisible();
        await expect(pm.locator('tr')).toHaveCount(3);
        await expect(pm.locator('th')).toHaveCount(3); // first row is a header row

        // Type into the first header cell and the first body cell.
        await pm.locator('th').first().click();
        await page.keyboard.type('Plan');
        await pm.locator('tbody tr:nth-child(2) td').first().click();
        await page.keyboard.type('Starter');

        // Add a row below the current (body) row.
        await modal.locator('.table-editor-toolbar__btn[title="Add row below"]').click();
        await expect(pm.locator('tr')).toHaveCount(4);

        // Toggle the header row off and back on (exercises toggleHeaderRow).
        const headerBtn = modal.locator('.table-editor-toolbar__btn[title="Toggle header row"]');
        await pm.locator('tr').first().locator('th, td').first().click();
        await headerBtn.click();
        await expect(pm.locator('th')).toHaveCount(0);
        await headerBtn.click();
        await expect(pm.locator('th')).toHaveCount(3);

        // Merge the 2nd and 3rd cells of the second row.
        const row2 = pm.locator('tbody tr:nth-child(2) td');
        const c2 = await row2.nth(1).boundingBox();
        const c3 = await row2.nth(2).boundingBox();
        if (!c2 || !c3) throw new Error('merge cells not laid out');
        await dragSelect(page, c2, c3);
        await modal.locator('.table-editor-toolbar__btn[title="Merge selected cells"]').click();
        await expect(pm.locator('tbody tr:nth-child(2) td')).toHaveCount(2);

        // Close the editor.
        await modal.locator('.table-editor-modal__done').click();
        await expect(modal).toBeHidden();

        // Autosave is debounced — poll the DB until the table HTML persists.
        await expect
            .poll(async () => {
                const cfg = await getWidgetConfig(widgetId);
                return typeof cfg.table_html === 'string' ? cfg.table_html : '';
            }, { timeout: 10_000 })
            .toContain('Plan');

        const cfg = await getWidgetConfig(widgetId);
        expect(cfg.table_html).toContain('Starter');
        expect(cfg.table_html).toContain('<th>');
        expect(cfg.table_html).toContain('colspan="2"');

        // Public render: the table content survives the round-trip.
        const slug = await getPageSlug(pageId);
        const url = slug === 'home' ? '/' : `/${slug}`;
        await page.goto(url);
        const publicTable = page.locator(`#widget-${widgetId} .np-table table`);
        await expect(publicTable).toBeVisible({ timeout: 10_000 });
        await expect(publicTable.locator('th', { hasText: 'Plan' })).toBeVisible();
        await expect(publicTable).toContainText('Starter');
    });
});
