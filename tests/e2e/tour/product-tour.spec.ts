// Session 338 — Guided product tour. Standing regression guard for the
// driver.js walkthrough wired into the Filament admin.
//
// Walks the full tour as the seeded super-admin: it launches from the dashboard,
// crosses real pages, the two interactive steps advance on the user's own click
// (the contact row → the record; "View transactions" → the ledger), every
// anchored step resolves to a visible spotlight, the active step survives a
// hard reload (multi-page resume), and it ends clean.
//
// Needs the curated demo baseline (the "hero" contact the record flow opens to),
// which the standard seed does not create — so we seed it here. Also guards the
// shared permission-matrix partial via the Roles showcase.

import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { composeExecArgs } from '../helpers/stack.js';

const title = (page) => page.locator('.driver-popover-title');
const nextBtn = (page) => page.locator('.driver-popover-next-btn');
// The real spotlighted element — never driver's centered placeholder.
const active = (page) => page.locator('.driver-active-element:not(#driver-dummy-element)');

// title, how the step advances, and whether it spotlights an element.
const STEPS = [
    { title: 'Welcome to your CRM', advance: 'next', anchored: false },
    { title: 'Contacts', advance: 'click', anchored: true },
    { title: 'One record, the whole picture', advance: 'next', anchored: true },
    { title: 'Their giving & payments', advance: 'click', anchored: true },
    { title: 'Payments & accounting', advance: 'next', anchored: true },
    { title: 'Mailing lists', advance: 'next', anchored: true },
    { title: 'Events & your website', advance: 'next', anchored: true },
    { title: 'Bring your data in (and out)', advance: 'next', anchored: false },
    { title: 'Roles & permissions', advance: 'next', anchored: true },
    { title: 'That’s the tour', advance: 'next', anchored: true },
    { title: 'Help is always one click away', advance: 'done', anchored: true },
];

test.beforeAll(() => {
    // The record flow opens to a "hero" contact; the default seed makes none,
    // and the e2e image is built --no-dev (no Faker for the full baseline), so
    // create one bare contact via Eloquent. The tour resolves anchors regardless
    // of its giving data — the rich demo hero is seeded by DemoBaselineSeeder in
    // the (Faker-equipped) authoring env, not here.
    const php = [
        'require "/var/www/html/vendor/autoload.php";',
        '$app = require "/var/www/html/bootstrap/app.php";',
        '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();',
        '$c = App\\Models\\Contact::firstOrCreate(["email" => "tour.hero@nphelper.demo"], ["first_name" => "Patricia", "last_name" => "Donovan"]);',
        '$c->forceFill(["created_at" => now()])->save();',
    ].join(' ');
    execFileSync('docker', composeExecArgs(['php', '-r', php]), {
        stdio: 'inherit',
        timeout: 60_000,
    });
});

async function launch(page) {
    await page.goto('/admin');
    await page.locator('[data-np-tour-start]').first().click();
    await expect(title(page)).toHaveText(STEPS[0].title, { timeout: 30_000 });
}

test('launches and walks every beat, advancing the two interactive steps by clicking', async ({ page }) => {
    await launch(page);

    for (const step of STEPS) {
        await expect(title(page)).toHaveText(step.title, { timeout: 30_000 });
        if (step.anchored) {
            await expect(active(page)).toBeVisible({ timeout: 30_000 });
        }
        if (step.advance === 'click') {
            // Interactive step: the user clicks the real element (a link), which
            // navigates and carries the tour forward.
            const link = active(page).locator('a').first();
            if (await link.count()) {
                await link.click();
            } else {
                await active(page).click();
            }
        } else {
            await nextBtn(page).click();
        }
    }

    await expect(page.locator('.driver-popover')).toHaveCount(0, { timeout: 15_000 });
    const state = await page.evaluate(() => localStorage.getItem('np-tour'));
    expect(state).toBeNull();
});

test('resumes the active step across a full-page reload', async ({ page }) => {
    await launch(page);

    // Welcome → Contacts is a real navigation; reload must land back on Contacts.
    await nextBtn(page).click();
    await expect(title(page)).toHaveText('Contacts', { timeout: 30_000 });

    await page.reload();
    await expect(title(page)).toHaveText('Contacts', { timeout: 30_000 });
});

test('the topbar "View site" link opens in a new tab', async ({ page }) => {
    await page.goto('/admin');
    await expect(page.locator('[data-tour="topbar.view-site"]')).toHaveAttribute('target', '_blank');
});

test('the Roles showcase renders the shared permission matrix', async ({ page }) => {
    await page.goto('/admin/roles-showcase');
    await expect(page.getByText('Resource', { exact: true })).toBeVisible({ timeout: 30_000 });
    await expect(page.locator('input[type=checkbox]').first()).toBeVisible();
});
