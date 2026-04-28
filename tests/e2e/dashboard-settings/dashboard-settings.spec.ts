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

    test('loads, adds a widget, and the DB reflects the new arrangement', async ({ page }) => {
        const configId = await findDashboardConfigIdByRoleName('super_admin');
        expect(configId).not.toBeNull();

        const before = await countDashboardWidgets(configId!);
        expect(before).toBe(3);

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
