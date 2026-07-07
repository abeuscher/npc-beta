// Session 362 — Tour B (CRM deep-dive). Standing regression guard.
//
// The CRM tour runs on the Contacts list with one contained hop: the user's own
// click on the seeded hero contact carries the tour into that record (via the
// one-shot launch flag), where the remaining steps finish. Every anchored step
// must resolve to a visible spotlight; the custom-fields step needs a contact
// custom field defined, which we seed here alongside the hero.

import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { composeExecArgs } from '../helpers/stack.js';

const title = (page) => page.locator('.driver-popover-title');
const nextBtn = (page) => page.locator('.driver-popover-next-btn');
const active = (page) => page.locator('.driver-active-element:not(#driver-dummy-element)');

test.beforeAll(() => {
    // The record hop opens the "hero" contact (the newest row); the standard
    // seed makes none, and the custom-fields step anchors a form section that
    // only renders when a contact custom field exists.
    const php = [
        'require "/var/www/html/vendor/autoload.php";',
        '$app = require "/var/www/html/bootstrap/app.php";',
        '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();',
        '$c = App\\Models\\Contact::firstOrCreate(["email" => "tour.hero@nphelper.demo"], ["first_name" => "Patricia", "last_name" => "Donovan"]);',
        '$c->forceFill(["created_at" => now()])->save();',
        'App\\Models\\CustomFieldDef::firstOrCreate(["model_type" => "contact", "handle" => "tour_notes"], ["label" => "Tour Notes", "field_type" => "text", "sort_order" => 1, "is_filterable" => false]);',
    ].join(' ');
    execFileSync('docker', composeExecArgs(['php', '-r', php]), {
        stdio: 'inherit',
        timeout: 60_000,
    });
});

test('walks the contacts list, drills into the hero record on the user\'s click, and finishes there', async ({ page }) => {
    // Deep-link entry: the same one-shot launch flag the dashboard tour's
    // conclusion link sets.
    await page.goto('/admin');
    await page.evaluate(() => {
        sessionStorage.setItem('np-tour-launch', JSON.stringify({ tour: 'crm', index: 0 }));
    });
    await page.goto('/admin/contacts');

    // Segment 1 — the contacts list.
    await expect(title(page)).toHaveText('Intro', { timeout: 30_000 });
    await nextBtn(page).click();

    await expect(title(page)).toHaveText('Contacts List View', { timeout: 30_000 });
    await expect(active(page)).toBeVisible({ timeout: 30_000 });
    await nextBtn(page).click();

    await expect(title(page)).toHaveText('Import / Export', { timeout: 30_000 });
    await expect(active(page)).toBeVisible({ timeout: 30_000 });
    await nextBtn(page).click();

    // The contained hop: the spotlighted hero row's own link navigates, and the
    // launch flag continues the tour on the record. Next stays available as the
    // safety net for users who don't read the click invitation.
    await expect(title(page)).toHaveText('Contact Record View', { timeout: 30_000 });
    await expect(active(page)).toBeVisible({ timeout: 30_000 });
    await expect(nextBtn(page)).toBeVisible();
    await active(page).locator('a').first().click();
    await page.waitForURL(/\/admin\/contacts\/[^/]+\/edit/, { timeout: 30_000 });

    // Segment 2 — the hero record.
    for (const step of ['Contact Notes View', 'Custom Fields', 'Custom Views', 'Mailing Lists']) {
        await expect(title(page)).toHaveText(step, { timeout: 30_000 });
        await expect(active(page)).toBeVisible({ timeout: 30_000 });
        if (step !== 'Mailing Lists') {
            await nextBtn(page).click();
        }
    }

    await nextBtn(page).click();
    await expect(page.locator('.driver-popover')).toHaveCount(0, { timeout: 15_000 });
    const flag = await page.evaluate(() => sessionStorage.getItem('np-tour-launch'));
    expect(flag).toBeNull();
});

test('Next on the record step is the safety net — it navigates into the record too', async ({ page }) => {
    await page.goto('/admin');
    await page.evaluate(() => {
        sessionStorage.setItem('np-tour-launch', JSON.stringify({ tour: 'crm', index: 0 }));
    });
    await page.goto('/admin/contacts');

    for (const step of ['Intro', 'Contacts List View', 'Import / Export']) {
        await expect(title(page)).toHaveText(step, { timeout: 30_000 });
        await nextBtn(page).click();
    }

    await expect(title(page)).toHaveText('Contact Record View', { timeout: 30_000 });
    await nextBtn(page).click();
    await page.waitForURL(/\/admin\/contacts\/[^/]+\/edit/, { timeout: 30_000 });
    await expect(title(page)).toHaveText('Contact Notes View', { timeout: 30_000 });
});
