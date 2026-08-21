import { test, expect } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import {
    findDashboardConfigIdByRoleName,
    countDashboardWidgets,
    findDashboardWidgetIdByHandle,
    deleteDashboardWidget,
} from '../helpers/db.js';

test.describe.configure({ mode: 'serial' });

test.describe('Dashboard View', () => {
    let addedWidgetId: string | null = null;

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test.afterAll(async () => {
        if (addedWidgetId !== null) {
            await deleteDashboardWidget(addedWidgetId);
            addedWidgetId = null;
        }
    });

    test('loads the dashboard arrangement view with the expected widgets and no preset/spacing tabs', async ({ page }) => {
        const configId = await findDashboardConfigIdByRoleName('super_admin');
        expect(configId).not.toBeNull();

        // Not pinned to a literal: the seed's widget count is not what this
        // spec is about, and hardcoding it turns any dashboard-seeder change
        // into a spurious failure here. What matters is that the arrangement
        // view is non-empty going in, that every expected handle resolves
        // below, and that merely viewing the page mutates nothing — which the
        // before/after comparison at the end proves.
        const before = await countDashboardWidgets(configId!);
        expect(before).toBeGreaterThan(0);

        await page.goto('/admin/dashboard-view');
        await expect(page.getByText('Dashboard arrangement')).toBeVisible({ timeout: 15_000 });

        const existingHandles = ['memos', 'quick_actions', 'this_weeks_events'];
        for (const handle of existingHandles) {
            const widgetId = await findDashboardWidgetIdByHandle(configId!, handle);
            expect(widgetId).not.toBeNull();
        }

        const presetsTab = page.getByRole('button', { name: 'Presets' });
        await expect(presetsTab).toHaveCount(0);

        const marginTab = page.getByRole('button', { name: 'Margin & Padding' });
        await expect(marginTab).toHaveCount(0);

        const after = await countDashboardWidgets(configId!);
        expect(after).toBe(before);
    });
});
