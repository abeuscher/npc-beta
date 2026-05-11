import { test, expect } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import { createPublishedEventWithTiers, cleanupEventsBySlugPrefix } from '../helpers/db.js';

test.describe.configure({ mode: 'serial' });

test.describe('Event registration — ticket tier picker', () => {
    const SLUG_PREFIX = '278-tier-picker-';

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        await cleanupEventsBySlugPrefix(SLUG_PREFIX);
    });

    test.afterAll(async () => {
        await cleanupEventsBySlugPrefix(SLUG_PREFIX);
    });

    test('free event with no tiers renders no tier picker', async ({ page }) => {
        const { landingPageSlug } = await createPublishedEventWithTiers(
            'Free Community Event',
            `${SLUG_PREFIX}free-uncapped`,
            [],
        );

        await page.context().clearCookies();
        await page.goto(`/${landingPageSlug}`);

        await expect(page.getByRole('heading', { name: 'Register', exact: true })).toBeVisible();
        await expect(page.locator('input[name="ticket_tier_id"]')).toHaveCount(0);
        await expect(page.locator('button[type="submit"]', { hasText: 'Register for this event' })).toBeVisible();
    });

    test('single-tier paid event renders hidden tier_id input and price', async ({ page }) => {
        const { landingPageSlug, tierIds } = await createPublishedEventWithTiers(
            'Annual Gala',
            `${SLUG_PREFIX}single-paid`,
            [{ name: 'General', price: 25.0, capacity: 100 }],
        );

        await page.context().clearCookies();
        await page.goto(`/${landingPageSlug}`);

        await expect(page.getByText('Registration fee:').first()).toBeVisible();
        await expect(page.getByText('$25.00').first()).toBeVisible();
        const hidden = page.locator('input[type="hidden"][name="ticket_tier_id"]');
        await expect(hidden).toHaveCount(1);
        await expect(hidden).toHaveValue(tierIds[0]);
        await expect(page.locator('button[type="submit"]', { hasText: 'Register & pay' })).toBeVisible();
    });

    test('multi-tier paid event renders radio picker with prices and selects first available', async ({ page }) => {
        const { landingPageSlug, tierIds } = await createPublishedEventWithTiers(
            'Conference 2026',
            `${SLUG_PREFIX}multi-paid`,
            [
                { name: 'General', price: 25.0, capacity: 100 },
                { name: 'VIP', price: 100.0, capacity: 10 },
            ],
        );

        await page.context().clearCookies();
        await page.goto(`/${landingPageSlug}`);

        await expect(page.getByText('Choose ticket type').first()).toBeVisible();

        const radios = page.locator('input[type="radio"][name="ticket_tier_id"]');
        await expect(radios).toHaveCount(2);

        // The first radio is checked by default
        await expect(radios.first()).toBeChecked();
        await expect(radios.first()).toHaveValue(tierIds[0]);

        // Tier labels render with name + price
        await expect(page.getByText('General — $25.00')).toBeVisible();
        await expect(page.getByText('VIP — $100.00')).toBeVisible();

        // Switching to the VIP tier wires the radio
        await radios.nth(1).check();
        await expect(radios.nth(1)).toBeChecked();
    });
});
