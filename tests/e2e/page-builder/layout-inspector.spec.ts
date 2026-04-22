import { test, expect } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import {
    findPageIdBySlug,
    createLayoutOnPage,
    getLayoutAppearanceConfig,
    deleteLayout,
} from '../helpers/db.js';

test.describe.configure({ mode: 'serial' });

test.describe('Page builder — layout inspector', () => {
    let createdLayoutId: string | null = null;

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test.afterAll(async () => {
        if (createdLayoutId !== null) {
            await deleteLayout(createdLayoutId);
            createdLayoutId = null;
        }
    });

    test('drives the three-tab layout inspector and persists background + padding', async ({ page }) => {
        const pageId = await findPageIdBySlug('home');
        expect(pageId).not.toBeNull();

        const layoutId = await createLayoutOnPage(pageId!);
        createdLayoutId = layoutId;

        // Navigate to the page builder for the home page.
        await page.goto(`/admin/pages/${pageId}/edit`);

        // Click the layout's select affordance. The layout inspector mounts
        // with a "Column Layout" type badge when a layout is selected.
        const layoutRegion = page.locator(`[data-layout-id="${layoutId}"]`);
        await expect(layoutRegion).toBeVisible({ timeout: 15_000 });
        await layoutRegion.locator('.layout-region__selector').click();

        const inspector = page.locator('.layout-inspector');
        await expect(inspector).toBeVisible();
        await expect(inspector.locator('.layout-inspector__type-badge')).toHaveText('Column Layout');

        // All three tabs should be present.
        const tabStrip = inspector.locator('.inspector-tabs');
        await expect(tabStrip.getByRole('button', { name: 'Column Settings' })).toBeVisible();
        await expect(tabStrip.getByRole('button', { name: 'Margin & Padding' })).toBeVisible();
        await expect(tabStrip.getByRole('button', { name: 'Background' })).toBeVisible();

        // Column Settings tab is active by default — Full width checkbox lives there.
        await expect(inspector.getByText('Full width')).toBeVisible();

        // Switch to Margin & Padding — the shared SectionLayoutPanel should mount.
        await tabStrip.getByRole('button', { name: 'Margin & Padding' }).click();
        const layoutPanel = inspector.locator('.layout-panel');
        await expect(layoutPanel).toBeVisible();

        // Set padding top to 24. The SpacingInput component renders one number
        // input per side — first input in the Padding group is "top".
        const paddingInputs = layoutPanel.locator('input[type="number"]');
        // Padding group is the first spacing-input; top is the first input of that group.
        await paddingInputs.first().fill('24');
        await paddingInputs.first().press('Tab');

        // Switch to Background — the shared BackgroundPanel should mount.
        await tabStrip.getByRole('button', { name: 'Background' }).click();
        const bgPanel = inspector.locator('.bg-panel');
        await expect(bgPanel).toBeVisible();

        // Image tab should NOT appear for layouts (showImage=false).
        await expect(bgPanel.locator('.bg-panel__swatch--image')).toHaveCount(0);

        // The Color sub-panel is open by default for a layout with no
        // background set (initialTab() in BackgroundPanel).
        const colorInput = bgPanel.locator('.color-picker__hex');
        await expect(colorInput).toBeVisible();
        await colorInput.fill('#123456');
        await colorInput.press('Tab');

        // Give the debounced save time to flush (store uses ~500ms debounce).
        await page.waitForTimeout(1200);

        // Reload and verify persistence via DB.
        const appearance = await getLayoutAppearanceConfig(layoutId);
        expect(appearance).toMatchObject({
            background: { color: '#123456' },
            layout: { padding: { top: 24 } },
        });
    });
});
