// Session 362 — Tour A (Dashboard overview). Standing regression guard.
//
// The dashboard tour stays on /admin: it walks the sidebar's major areas plus
// the help search, every anchored step resolving to a visible spotlight, and
// ends in a centered conclusion popover whose links hand off to the CRM / CMS
// deep-dive tours via the one-shot launch flag.

import { test, expect } from '@playwright/test';

const title = (page) => page.locator('.driver-popover-title');
const nextBtn = (page) => page.locator('.driver-popover-next-btn');
// The real spotlighted element — never driver's centered placeholder.
const active = (page) => page.locator('.driver-active-element:not(#driver-dummy-element)');

const STEPS = [
    { title: 'CRM', anchored: true },
    { title: 'Memberships', anchored: true },
    { title: 'Donations', anchored: true },
    { title: 'Events', anchored: true },
    { title: 'CMS', anchored: true },
    { title: 'Mailing Lists', anchored: true },
    { title: 'Help System', anchored: true },
    { title: 'Conclusion', anchored: false },
];

async function launch(page) {
    await page.goto('/admin');
    await page.locator('[data-np-tour-start="dashboard"]').click();
    await expect(title(page)).toHaveText(STEPS[0].title, { timeout: 30_000 });
}

test('walks every sidebar beat on the dashboard and ends clean', async ({ page }) => {
    await launch(page);

    for (const step of STEPS) {
        await expect(title(page)).toHaveText(step.title, { timeout: 30_000 });
        if (step.anchored) {
            await expect(active(page)).toBeVisible({ timeout: 30_000 });
        }
        if (step.title !== 'Conclusion') {
            await nextBtn(page).click();
        }
    }

    // The conclusion offers both deep-dive handoffs, then Done ends the tour.
    await expect(page.locator('[data-np-tour-goto="crm"]')).toBeVisible();
    await expect(page.locator('[data-np-tour-goto="cms"]')).toBeVisible();
    await nextBtn(page).click();
    await expect(page.locator('.driver-popover')).toHaveCount(0, { timeout: 15_000 });
});

test('the conclusion link hands off to the CRM tour on the contacts page', async ({ page }) => {
    await launch(page);

    for (let i = 0; i < STEPS.length - 1; i++) {
        await nextBtn(page).click();
    }
    await expect(title(page)).toHaveText('Conclusion', { timeout: 30_000 });

    await page.locator('[data-np-tour-goto="crm"]').click();
    await page.waitForURL(/\/admin\/contacts/, { timeout: 30_000 });
    await expect(title(page)).toHaveText('Intro', { timeout: 30_000 });

    // The launch flag is one-shot — consumed on the destination page.
    const flag = await page.evaluate(() => sessionStorage.getItem('np-tour-launch'));
    expect(flag).toBeNull();
});
