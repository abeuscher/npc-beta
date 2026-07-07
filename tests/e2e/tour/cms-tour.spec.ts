// Session 362 — Tour C (CMS deep-dive). Standing regression guard.
//
// The CMS tour is fully single-page: it runs on the Pages list and points each
// capability at its sidebar home, ending in a centered conclusion. Every
// anchored step must resolve to a visible spotlight.

import { test, expect } from '@playwright/test';

const title = (page) => page.locator('.driver-popover-title');
const nextBtn = (page) => page.locator('.driver-popover-next-btn');
const active = (page) => page.locator('.driver-active-element:not(#driver-dummy-element)');

const STEPS = [
    { title: 'CMS', anchored: true },
    { title: 'Widgets', anchored: true },
    { title: 'Forms', anchored: true },
    { title: 'Templates', anchored: true },
    { title: 'Theming', anchored: true },
    { title: 'Custom Snippets', anchored: true },
    { title: 'Conclusion', anchored: false },
];

test('walks the CMS beats on the pages list and ends clean', async ({ page }) => {
    await page.goto('/admin');
    await page.evaluate(() => {
        sessionStorage.setItem('np-tour-launch', JSON.stringify({ tour: 'cms', index: 0 }));
    });
    await page.goto('/admin/pages');

    for (const step of STEPS) {
        await expect(title(page)).toHaveText(step.title, { timeout: 30_000 });
        if (step.anchored) {
            await expect(active(page)).toBeVisible({ timeout: 30_000 });
        }
        if (step.title !== 'Conclusion') {
            await nextBtn(page).click();
        }
    }

    await nextBtn(page).click();
    await expect(page.locator('.driver-popover')).toHaveCount(0, { timeout: 15_000 });
    const flag = await page.evaluate(() => sessionStorage.getItem('np-tour-launch'));
    expect(flag).toBeNull();
});
