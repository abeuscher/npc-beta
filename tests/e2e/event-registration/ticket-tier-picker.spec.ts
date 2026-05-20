import { test, expect } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import { createPublishedEventWithTiers, cleanupEventsBySlugPrefix, fillTierToCapacity } from '../helpers/db.js';

// SKIPPED (e2e-stabilization handback). This suite is the only one that
// remained intermittently unstable in CI after the two shared infra causes
// were fixed: it creates a brand-new published event + page +
// event_registration widget per test via raw SQL and immediately navigates
// to that *freshly-created* public page. It is uniquely exposed to (a) the
// cold first render of a not-pre-warmed page and (b) a likely
// event_registration widget dependency on the public widget bundle, which
// the isolated e2e stack deliberately does not build (no build server in
// CI). Across runs it flipped pass / flaky / hard-fail. Owner decision:
// skip it for now rather than block the suite; flagged in
// sessions/housekeeping-inbox.md ([test-integrity]) to be revisited — and
// most likely deleted — in the next housekeeping session unless we decide
// to give the e2e stack the widget bundle / harden the per-test setup.
test.describe.configure({ mode: 'serial', retries: 2 });

test.describe.skip('Event registration — quantity spinner', () => {
    const SLUG_PREFIX = '279-quantity-spinner-';

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        await cleanupEventsBySlugPrefix(SLUG_PREFIX);
    });

    test.afterAll(async () => {
        await cleanupEventsBySlugPrefix(SLUG_PREFIX);
    });

    test('free event with no tiers renders no quantities UI', async ({ page }) => {
        const { landingPageSlug } = await createPublishedEventWithTiers(
            'Free Community Event',
            `${SLUG_PREFIX}free-uncapped`,
            [],
        );

        await page.context().clearCookies();
        await page.goto(`/${landingPageSlug}`);
        await page.waitForLoadState('networkidle');

        // First public-page render can be slow cold (runtime SCSS compile)
        // on a contended box; gate generously before asserting structure.
        await expect(page.getByRole('heading', { name: 'Register', exact: true })).toBeVisible({ timeout: 20_000 });
        await expect(page.locator('input[name^="quantities["]')).toHaveCount(0);
        await expect(page.locator('button[type="submit"]', { hasText: 'Register for this event' })).toBeVisible();
    });

    test('single-tier paid event renders one quantity spinner with default 1 and a live subtotal', async ({ page }) => {
        const { landingPageSlug, tierIds } = await createPublishedEventWithTiers(
            'Annual Gala',
            `${SLUG_PREFIX}single-paid`,
            [{ name: 'General', price: 25.0, capacity: 100 }],
        );

        await page.context().clearCookies();
        await page.goto(`/${landingPageSlug}`);
        await page.waitForLoadState('networkidle');

        const input = page.locator(`input[name="quantities[${tierIds[0]}]"]`);
        await expect(input).toHaveCount(1, { timeout: 15_000 });
        await expect(input).toHaveAttribute('type', 'number');
        await expect(input).toHaveValue('1');
        await expect(input).toHaveAttribute('max', '100');

        // Subtotal renders at $25.00 for default qty=1 (computed by front-end
        // JS after hydration — allow for hydration latency under load)
        await expect(page.locator('[data-event-registration-subtotal]')).toHaveText('25.00', { timeout: 15_000 });

        // Bumping the spinner updates the subtotal live
        await input.fill('3');
        await input.dispatchEvent('input');
        await expect(page.locator('[data-event-registration-subtotal]')).toHaveText('75.00', { timeout: 10_000 });

        await expect(page.locator('button[type="submit"]', { hasText: 'Register & pay' })).toBeVisible();
    });

    test('multi-tier event renders one spinner per tier and sums the subtotal across them', async ({ page }) => {
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
        await page.waitForLoadState('networkidle');

        const general = page.locator(`input[name="quantities[${tierIds[0]}]"]`);
        const vip     = page.locator(`input[name="quantities[${tierIds[1]}]"]`);

        await expect(general).toHaveCount(1, { timeout: 15_000 });
        await expect(vip).toHaveCount(1);
        // Default 0 in multi-tier mode
        await expect(general).toHaveValue('0');
        await expect(vip).toHaveValue('0');
        await expect(page.locator('[data-event-registration-subtotal]')).toHaveText('0.00');

        // 2 General + 1 VIP → $50 + $100 = $150.00
        await general.fill('2');
        await general.dispatchEvent('input');
        await vip.fill('1');
        await vip.dispatchEvent('input');
        await expect(page.locator('[data-event-registration-subtotal]')).toHaveText('150.00');

        await expect(page.getByText('General').first()).toBeVisible();
        await expect(page.getByText('VIP').first()).toBeVisible();
    });

    test('sold-out tier disables the spinner at max=0', async ({ page }) => {
        const { landingPageSlug, tierIds } = await createPublishedEventWithTiers(
            'Sold Out Tier Event',
            `${SLUG_PREFIX}sold-out`,
            [
                { name: 'General', price: 25.0, capacity: 5 },
                { name: 'VIP', price: 100.0, capacity: 1 },
            ],
        );

        // Fill the VIP tier to capacity directly.
        await fillTierToCapacity(`${SLUG_PREFIX}sold-out`, tierIds[1], 1);

        await page.context().clearCookies();
        await page.goto(`/${landingPageSlug}`);
        await page.waitForLoadState('networkidle');

        const general = page.locator(`input[name="quantities[${tierIds[0]}]"]`);
        const vip     = page.locator(`input[name="quantities[${tierIds[1]}]"]`);

        await expect(general).toBeEnabled({ timeout: 15_000 });
        await expect(general).toHaveAttribute('max', '5');

        await expect(vip).toBeDisabled();
        await expect(vip).toHaveAttribute('max', '0');
        await expect(page.getByText('(sold out)').first()).toBeVisible();
    });
});
