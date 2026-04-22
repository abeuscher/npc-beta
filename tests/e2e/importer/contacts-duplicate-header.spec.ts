import { test, expect } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import {
    cleanCsvPath,
    FAKE_SOURCE_LABELS,
    parseCsv,
    primeFakeFixtures,
    tempCsvPath,
    writeCsv,
} from '../helpers/fake-csv.js';
import { fillUploadStep, openContactsWizard } from '../helpers/wizard.js';
import {
    cleanupAllImportSessionsOfType,
    findLatestImportSessionId,
    countContactsInSession,
} from '../helpers/db.js';

test.describe.configure({ mode: 'serial' });

test.describe('Contacts importer — duplicate header review edge case', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        primeFakeFixtures(4202);
    });

    test.afterAll(async () => {
        await cleanupAllImportSessionsOfType('contact');
    });

    test('duplicate Email header surfaces in review step and import proceeds with default ignore', async ({ page }) => {
        test.setTimeout(180_000);

        const clean = parseCsv(cleanCsvPath('contacts.csv'));
        const emailIdx = clean.headers.indexOf('Email');
        expect(emailIdx).toBeGreaterThanOrEqual(0);

        const mutatedHeaders = [...clean.headers, 'Email'];
        const mutatedRows = clean.rows.map((r) => [...r, r[emailIdx] ?? '']);
        const tmp = tempCsvPath('contacts-duplicate-header');
        writeCsv(tmp, mutatedHeaders, mutatedRows);

        await openContactsWizard(page);
        await fillUploadStep(page, {
            sessionLabel: 'E2E Duplicate Header',
            sourceName_reuse: FAKE_SOURCE_LABELS.contacts,
            csvPath: tmp,
        });

        await page.getByTestId('import-step-next-0').click();

        // Review step: duplicate-header finding surfaces.
        await expect(page.getByTestId('import-review-findings')).toBeVisible();
        await expect(page.getByTestId('import-review-findings')).toContainText(/finding/i);

        // Accept the default review decisions (first Email = keep, duplicate = ignore).
        await page.getByTestId('import-step-next-1').click();
        await expect(page.getByTestId('import-match-key')).toBeVisible();

        await page.getByTestId('import-duplicate-strategy')
            .locator('input[type="radio"][value="skip"]')
            .check();

        await page.getByTestId('import-step-next-2').click();
        await expect(page.getByTestId('import-commit-button')).toBeVisible();

        await page.getByTestId('import-commit-button').click();
        await expect(page.getByTestId('import-progress-phase-awaiting')).toBeVisible({ timeout: 60_000 });

        await page.getByTestId('import-progress-commit-button').click();
        await expect(page.getByTestId('import-progress-phase-done')).toBeVisible({ timeout: 180_000 });

        // Import completed on the surviving (non-ignored) Email column.
        const sessionId = await findLatestImportSessionId();
        expect(sessionId).not.toBeNull();
        const count = await countContactsInSession(sessionId!);
        expect(count).toBeGreaterThan(0);
    });
});
