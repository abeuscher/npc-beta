import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';
import { resetAndLogin } from '../helpers/auth.js';
import { fillUploadStep } from '../helpers/wizard.js';
import { cleanupAllImportSessionsOfType } from '../helpers/db.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const FIXTURE_CSV = path.resolve(__dirname, '../fixtures/donations/happy-path.csv');

test.describe.configure({ mode: 'serial' });

test.describe('Donations importer — Map Columns row indicator', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test.afterAll(async () => {
        await cleanupAllImportSessionsOfType('donation');
    });

    test('rows render incomplete by default and flip to complete when mapped', async ({ page }) => {
        test.setTimeout(120_000);

        await page.goto('/admin/import-donations-page');
        await expect(page.getByTestId('import-donations-wizard')).toBeVisible();

        await fillUploadStep(page, {
            sessionLabel: 'E2E Mapping Indicator',
            sourceName: 'E2E Mapping Indicator Source',
            csvPath: FIXTURE_CSV,
        });

        await page.getByTestId('import-step-next-0').click();
        await expect(page.getByTestId('import-step-next-1')).toBeVisible();
        await page.getByTestId('import-step-next-1').click();
        await expect(page.getByTestId('import-contact-match-key')).toBeVisible();

        const completeRows = page.locator('.np-import-map-row--complete');
        const incompleteRows = page.locator('.np-import-map-row--incomplete');

        await expect(completeRows).not.toHaveCount(0);
        await expect(incompleteRows).not.toHaveCount(0);

        const externalIdColIndex = 3;
        const externalIdRow = page.getByTestId(`map-column-${externalIdColIndex}`).locator('xpath=ancestor::*[contains(@class, "np-import-map-row")][1]');

        await expect(externalIdRow).toHaveClass(/np-import-map-row--incomplete/);

        const externalIdSelect = page.getByTestId(`map-column-${externalIdColIndex}`).locator('select');
        await externalIdSelect.selectOption('donation:external_id');
        await page.waitForLoadState('networkidle');

        await expect(externalIdRow).toHaveClass(/np-import-map-row--complete/);
        await expect(externalIdRow).not.toHaveClass(/np-import-map-row--incomplete/);
    });

    test('mapping select exposes a search input when opened', async ({ page }) => {
        test.setTimeout(60_000);

        await page.goto('/admin/import-donations-page');
        await expect(page.getByTestId('import-donations-wizard')).toBeVisible();

        await fillUploadStep(page, {
            sessionLabel: 'E2E Mapping Search',
            sourceName: 'E2E Mapping Search Source',
            csvPath: FIXTURE_CSV,
        });

        await page.getByTestId('import-step-next-0').click();
        await expect(page.getByTestId('import-step-next-1')).toBeVisible();
        await page.getByTestId('import-step-next-1').click();
        await expect(page.getByTestId('import-contact-match-key')).toBeVisible();

        const externalIdRow = page.getByTestId('map-column-3');

        await externalIdRow.locator('.choices').click();

        await expect(externalIdRow.locator('.choices__input--cloned')).toBeVisible();
    });
});
