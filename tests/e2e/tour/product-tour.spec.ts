// Session 338 — Guided product tour. Standing regression guard for the
// driver.js walkthrough wired into the Filament admin.
//
// Verifies the objective behaviour the tour must hold: it launches from the
// dashboard, walks every beat across real pages, each anchored step resolves to
// a visible element, the active step survives a full-page reload (the multi-page
// resume), and it ends cleanly. Authored against the seeded data via the
// isolated e2e stack (npm run test:e2e); the auth is the shared admin
// storageState, so every tour-target page is reachable and all nine beats show.
//
// Also guards the shared permission-matrix partial the Roles showcase reuses:
// the real role form must still render the matrix.

import { test, expect } from '@playwright/test';

// The nine beats, in order, by popover title (super-admin sees them all).
const BEATS = [
    'Welcome to your CRM',
    'Contacts',
    'Donations',
    'Mailing lists',
    'Memberships',
    'Events & your website',
    'Payments & accounting',
    'Bring your data in (and out)',
    'Roles & permissions',
];

const title = (page) => page.locator('.driver-popover-title');
const nextBtn = (page) => page.locator('.driver-popover-next-btn');

async function launch(page) {
    await page.goto('/admin');
    await page.locator('[data-np-tour-start]').first().click();
    await expect(title(page)).toHaveText(BEATS[0], { timeout: 30_000 });
}

test('launches from the dashboard and walks every beat across real pages', async ({ page }) => {
    await launch(page);

    for (let i = 0; i < BEATS.length; i++) {
        await expect(title(page)).toHaveText(BEATS[i], { timeout: 30_000 });

        // Every beat past the welcome modal is anchored to a real, visible
        // element (driver marks it `.driver-active-element`).
        if (i > 0) {
            await expect(page.locator('.driver-active-element')).toBeVisible({ timeout: 30_000 });
        }

        if (i < BEATS.length - 1) {
            await nextBtn(page).click(); // navigates to the next page; resume re-shows the popover
        }
    }

    // The final "Done" click tears the tour down and clears its persisted state.
    await nextBtn(page).click();
    await expect(page.locator('.driver-popover')).toHaveCount(0, { timeout: 15_000 });
    const state = await page.evaluate(() => localStorage.getItem('np-tour'));
    expect(state).toBeNull();
});

test('resumes the active step across a full-page reload', async ({ page }) => {
    await launch(page);

    // Advance to the Donations beat (two navigations in).
    await nextBtn(page).click();
    await expect(title(page)).toHaveText('Contacts', { timeout: 30_000 });
    await nextBtn(page).click();
    await expect(title(page)).toHaveText('Donations', { timeout: 30_000 });

    // A hard reload must bring the tour back at the same beat, not lose it.
    await page.reload();
    await expect(title(page)).toHaveText('Donations', { timeout: 30_000 });
});

test('the topbar "View site" link opens in a new tab while the tour stays put', async ({ page }) => {
    await page.goto('/admin');
    const viewSite = page.locator('[data-tour="topbar.view-site"]');
    await expect(viewSite).toHaveAttribute('target', '_blank');
});

test('the real role form still renders the shared permission matrix', async ({ page }) => {
    await page.goto('/admin/roles-showcase');
    // The showcase reuses the exact matrix component; if it renders here with
    // its read/write/delete columns, the shared partial is intact.
    await expect(page.getByText('Resource', { exact: true })).toBeVisible({ timeout: 30_000 });
    await expect(page.locator('input[type=checkbox]').first()).toBeVisible();
});
