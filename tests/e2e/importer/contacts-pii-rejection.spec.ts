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
import {
    advanceThroughMapping,
    fillUploadStep,
    openContactsWizard,
} from '../helpers/wizard.js';

test.describe.configure({ mode: 'serial' });

test.describe('Contacts importer — PII rejection edge case', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        primeFakeFixtures(4201);
    });

    test('contacts CSV with SSN-format values in an extra column is rejected', async ({ page }) => {
        test.setTimeout(180_000);

        const clean = parseCsv(cleanCsvPath('contacts.csv'));
        const mutatedHeaders = [...clean.headers, 'Identifier'];
        const mutatedRows = clean.rows.map((r, i) => [...r, syntheticSsn(i)]);
        const tmp = tempCsvPath('contacts-pii');
        writeCsv(tmp, mutatedHeaders, mutatedRows);

        await openContactsWizard(page);
        await fillUploadStep(page, {
            sessionLabel: 'E2E PII rejection',
            sourceName_reuse: FAKE_SOURCE_LABELS.contacts,
            csvPath: tmp,
        });
        await advanceThroughMapping(page, {
            sessionLabel: 'E2E PII rejection',
            csvPath: tmp,
        });

        await page.getByTestId('import-commit-button').click();
        await expect(page.getByTestId('import-progress-phase-rejected')).toBeVisible({ timeout: 60_000 });

        await expect(page.getByTestId('import-pii-count')).toBeVisible();
        await expect(page.getByTestId('import-pii-count')).toContainText(/flagged row/i);

        const downloadPromise = page.waitForEvent('download');
        await page.getByTestId('import-download-pii').click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toContain('pii-violations');
    });
});

function syntheticSsn(index: number): string {
    const a = String(100 + (index * 7) % 900).padStart(3, '0');
    const b = String(10 + (index * 13) % 90).padStart(2, '0');
    const c = String(1000 + (index * 17) % 9000).padStart(4, '0');
    return `${a}-${b}-${c}`;
}
