import { test, expect } from '@playwright/test';
import type { Locator } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import {
    findPageIdBySlug,
    createWidgetOnPage,
    setWidgetConfig,
    getWidgetConfig,
    deleteWidget,
} from '../helpers/db.js';

// Session 304 Phase 2 — in-page text editing. The interaction model from
// Phase 1 is the substrate; here a selected, code-declared-eligible widget
// exposes its annotated prose nodes as in-place editors that write the RAW
// config value (incl. nested repeater paths), with the token / data-driven
// negative gates and the selection-scoped discoverability model.

test.describe.configure({ mode: 'serial' });

async function centre(locator: Locator): Promise<void> {
    await locator.scrollIntoViewIfNeeded();
    await locator.evaluate((el) => el.scrollIntoView({ block: 'center', inline: 'center' }));
}

// Deterministic select regardless of prior selection: the Phase 1 Edit
// affordance always sits above the overlay and selects + opens the
// Inspector. (Overlay-click only selects when not already inline-armed.)
async function selectWidget(region: Locator): Promise<void> {
    await centre(region);
    await region.locator('.preview-region__edit').click();
    await expect(region).toHaveClass(/preview-region--selected/);
}

test.describe('Page builder — in-page text editing (session 304 Phase 2)', () => {
    let pageId: string;
    let pricing: string;
    let tokenTb: string;

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        const id = await findPageIdBySlug('home');
        if (!id) throw new Error('home page not found');
        pageId = id;

        pricing = await createWidgetOnPage(pageId, 'pricing_chart');
        await setWidgetConfig(pricing, {
            heading: '',
            columns: [
                {
                    emphasize: false,
                    eyebrow: '',
                    title: 'Basic',
                    price: '',
                    lead_content: '',
                    attribute_rows: [{ label: 'Seats', value: '<p>five</p>' }],
                    ctas: [],
                },
            ],
        });

        tokenTb = await createWidgetOnPage(pageId, 'text_block');
        await setWidgetConfig(tokenTb, { content: '<p>Hello {{site.name}} world</p>' });
    });

    test.afterAll(async () => {
        for (const id of [pricing, tokenTb]) {
            if (id) await deleteWidget(id);
        }
    });

    test('unselected preview is pixel-clean; selecting arms the prose nodes', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const region = page.locator(`.preview-region[data-widget-id="${pricing}"]`);
        const other = page.locator(`.preview-region[data-widget-id="${tokenTb}"]`);
        await expect(region).toBeVisible({ timeout: 15_000 });

        // Move selection away from pricing → it must be pixel-clean.
        await selectWidget(other);
        await expect(region).not.toHaveClass(/preview-region--inline-armed/);
        await expect(region.locator('.inline-editable')).toHaveCount(0);

        // Select pricing → its annotated prose nodes arm.
        await selectWidget(region);
        await expect(region).toHaveClass(/preview-region--inline-armed/);
        await expect(region.locator('.inline-editable').first()).toBeVisible();
        expect(await region.locator('.inline-editable').count()).toBeGreaterThan(0);

        // The empty heading slot is a discoverable wrapper with a
        // schema-label ghost placeholder.
        const heading = region.locator('[data-config-key="heading"]');
        await expect(heading).toHaveAttribute('data-config-placeholder', 'Heading');
        await expect(heading).toHaveText('');
    });

    test('round-trips a nested plaintext path (columns.0.title) to raw config', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const region = page.locator(`.preview-region[data-widget-id="${pricing}"]`);
        await expect(region).toBeVisible({ timeout: 15_000 });

        await selectWidget(region);
        const title = region.locator('[data-config-key="columns.0.title"]');
        await expect(title).toHaveClass(/inline-editable/);

        await centre(title);
        // First click arms+activates the contenteditable. Triple-click was
        // unreliable here (it raced the arm re-render → typed text appended,
        // "BasicStarter Plan"); select-all via keyboard then replace is
        // deterministic.
        await title.click();
        await title.click();
        await page.keyboard.press('ControlOrMeta+A');
        await page.keyboard.press('Delete');
        await page.keyboard.type('Starter Plan');
        // Explicit blur → commit + flush + reconciling refresh. The flush is
        // debounced/async; poll the persisted config instead of a fixed wait
        // (the fixed 1.5s was too short on a slow CI box → stale read).
        await title.evaluate((el) => (el as HTMLElement).blur());

        await expect
            .poll(async () => (await getWidgetConfig(pricing)).columns?.[0]?.title, {
                timeout: 15_000,
            })
            .toBe('Starter Plan');
    });

    test('round-trips a nested richtext path (columns.0.attribute_rows.0.value)', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const region = page.locator(`.preview-region[data-widget-id="${pricing}"]`);
        await expect(region).toBeVisible({ timeout: 15_000 });

        await selectWidget(region);
        const valNode = region.locator('[data-config-key="columns.0.attribute_rows.0.value"]');
        await expect(valNode).toHaveClass(/inline-editable/);

        await centre(valNode);
        await valNode.click(); // pointerdown mounts the no-toolbar Quill
        const editor = valNode.locator('.ql-editor');
        await expect(editor).toBeVisible({ timeout: 15_000 });
        await editor.fill('ten seats'); // contenteditable-aware: clears + types
        await editor.evaluate((el) => (el as HTMLElement).blur());
        await page.waitForTimeout(1800);

        const cfg = await getWidgetConfig(pricing);
        expect(JSON.stringify(cfg.columns?.[0]?.attribute_rows?.[0]?.value ?? '')).toContain('ten seats');
    });

    test('token-bearing node is editable and seeds the raw token', async ({ page }) => {
        await page.goto(`/admin/pages/${pageId}/edit`);
        const region = page.locator(`.preview-region[data-widget-id="${tokenTb}"]`);
        await expect(region).toBeVisible({ timeout: 15_000 });

        await selectWidget(region);
        await expect(region).toHaveClass(/preview-region--inline-armed/);

        // Owner decision (session 304): token-bearing prose is editable.
        // The editor seeds from / writes the RAW config, so the literal
        // {{site.name}} is shown and round-trips literally — substitution
        // still happens at render.
        const content = region.locator('[data-config-key="content"]');
        await expect(content).toHaveClass(/inline-editable/);
        await content.click();
        const editor = content.locator('.ql-editor');
        await expect(editor).toBeVisible({ timeout: 15_000 });
        await expect(editor).toContainText('{{site.name}}');
    });
});
