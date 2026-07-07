// Session 362 — throwaway storyboard capture (@on-demand): one screenshot per
// step of each tour, for owner copy/framing review. NOT a standing spec — torn
// down at session close per Playwright discipline.

import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { composeExecArgs } from '../helpers/stack.js';

const OUT = 'storyboard';

const title = (page) => page.locator('.driver-popover-title');
const nextBtn = (page) => page.locator('.driver-popover-next-btn');
const active = (page) => page.locator('.driver-active-element:not(#driver-dummy-element)');

const slug = (s: string) => s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

async function shoot(page, tour: string, index: number, stepTitle: string) {
    await page.waitForTimeout(600); // let driver's scroll/position settle for the still
    await page.screenshot({
        path: `${OUT}/${tour}/${String(index + 1).padStart(2, '0')}-${slug(stepTitle)}.png`,
        fullPage: false,
    });
}

test.describe('@on-demand tour storyboard', () => {
    test.beforeAll(() => {
        const php = [
            'require "/var/www/html/vendor/autoload.php";',
            '$app = require "/var/www/html/bootstrap/app.php";',
            '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();',
            '$c = App\\Models\\Contact::firstOrCreate(["email" => "tour.hero@nphelper.demo"], ["first_name" => "Patricia", "last_name" => "Donovan"]);',
            '$c->forceFill(["created_at" => now()])->save();',
            'App\\Models\\CustomFieldDef::firstOrCreate(["model_type" => "contact", "handle" => "tour_notes"], ["label" => "Tour Notes", "field_type" => "text", "sort_order" => 1, "is_filterable" => false]);',
        ].join(' ');
        execFileSync('docker', composeExecArgs(['php', '-r', php]), { stdio: 'inherit', timeout: 60_000 });
    });

    test('dashboard tour storyboard', async ({ page }) => {
        const steps = ['CRM', 'Memberships', 'Donations', 'Events', 'CMS', 'Mailing Lists', 'Help System', 'Conclusion'];
        await page.goto('/admin');
        await page.locator('[data-np-tour-start="dashboard"]').click();
        for (let i = 0; i < steps.length; i++) {
            await expect(title(page)).toHaveText(steps[i], { timeout: 30_000 });
            await shoot(page, 'dashboard', i, steps[i]);
            if (i < steps.length - 1) await nextBtn(page).click();
        }
    });

    test('crm tour storyboard', async ({ page }) => {
        await page.goto('/admin');
        await page.evaluate(() => sessionStorage.setItem('np-tour-launch', JSON.stringify({ tour: 'crm', index: 0 })));
        await page.goto('/admin/contacts');

        const listSteps = ['Intro', 'Contacts List View', 'Import / Export', 'Contact Record View'];
        for (let i = 0; i < listSteps.length; i++) {
            await expect(title(page)).toHaveText(listSteps[i], { timeout: 30_000 });
            await shoot(page, 'crm', i, listSteps[i]);
            if (i < listSteps.length - 1) await nextBtn(page).click();
        }
        await active(page).locator('a').first().click();
        await page.waitForURL(/\/admin\/contacts\/[^/]+\/edit/, { timeout: 30_000 });

        const recordSteps = ['Contact Notes View', 'Custom Fields', 'Custom Views', 'Mailing Lists'];
        for (let i = 0; i < recordSteps.length; i++) {
            await expect(title(page)).toHaveText(recordSteps[i], { timeout: 30_000 });
            await shoot(page, 'crm', 4 + i, recordSteps[i]);
            if (i < recordSteps.length - 1) await nextBtn(page).click();
        }
    });

    test('cms tour storyboard', async ({ page }) => {
        const steps = ['CMS', 'Widgets', 'Forms', 'Templates', 'Theming', 'Custom Snippets', 'Conclusion'];
        await page.goto('/admin');
        await page.evaluate(() => sessionStorage.setItem('np-tour-launch', JSON.stringify({ tour: 'cms', index: 0 })));
        await page.goto('/admin/pages');
        for (let i = 0; i < steps.length; i++) {
            await expect(title(page)).toHaveText(steps[i], { timeout: 30_000 });
            await shoot(page, 'cms', i, steps[i]);
            if (i < steps.length - 1) await nextBtn(page).click();
        }
    });
});
