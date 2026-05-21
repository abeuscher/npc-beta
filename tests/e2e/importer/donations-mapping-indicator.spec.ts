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

        // The .choices wrapper only exists once Choices.js has lazily
        // initialised; clicking before then drops the open. Gate on it.
        const choices = externalIdRow.locator('.choices');
        await expect(choices).toBeVisible({ timeout: 15_000 });
        await choices.click();

        await expect(externalIdRow.locator('.choices__input--cloned')).toBeVisible({ timeout: 10_000 });
    });
});
