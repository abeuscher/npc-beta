import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';
import { resetAndLogin } from '../helpers/auth.js';
import {
    cleanCsvPath,
    FAKE_SOURCE_LABELS,
    parseCsv,
    primeFakeFixtures,
    tempCsvPath,
    writeCsv,
} from '../helpers/fake-csv.js';
import { driveDonationsHappyPath } from '../helpers/wizard.js';
import { cleanupAllImportSessionsOfType, deleteContactsByEmails } from '../helpers/db.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const PROJECT_ROOT = path.resolve(__dirname, '../../..');

test.describe.configure({ mode: 'serial' });

test.describe('Donations importer — error report edge case', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        primeFakeFixtures(4203);
        insertAmbiguousContacts();
    });

    test.afterAll(async () => {
        await cleanupAllImportSessionsOfType('donation');
        await deleteContactsByEmails([
            'ambig1@example.com',
            'ambig2@example.com',
            'ambig3@example.com',
            'ambig4@example.com',
            'ambig5@example.com',
        ]);
    });

    test('donations CSV with 5 ambiguous-email rows surfaces 5-row error table and downloads errored-rows CSV', async ({ page }) => {
        test.setTimeout(300_000);

        const clean = parseCsv(cleanCsvPath('donations.csv'));
        const emailIdx = clean.headers.indexOf('Email');
        expect(emailIdx).toBeGreaterThanOrEqual(0);

        const ambiguousEmails = [
            'ambig1@example.com',
            'ambig2@example.com',
            'ambig3@example.com',
            'ambig4@example.com',
            'ambig5@example.com',
        ];
        const corruptIndices = [10, 20, 30, 40, 50];
        const mutatedRows = clean.rows.map((r) => [...r]);
        corruptIndices.forEach((rowIdx, i) => {
            mutatedRows[rowIdx][emailIdx] = ambiguousEmails[i];
        });

        const tmp = tempCsvPath('donations-errors');
        writeCsv(tmp, clean.headers, mutatedRows);

        await driveDonationsHappyPathUntilAwaiting(page, {
            sessionLabel: 'E2E Error Report',
            sourceName_reuse: FAKE_SOURCE_LABELS.donations,
            csvPath: tmp,
            contactMissingStrategy: 'auto_create',
        });

        await expect(page.getByTestId('import-progress-phase-awaiting')).toBeVisible({ timeout: 120_000 });
        await expect(page.getByTestId('import-error-table')).toBeVisible();
        await expect(page.getByTestId('import-error-count')).toContainText(/5 rows errored/i);

        const downloadPromise = page.waitForEvent('download');
        await page.getByTestId('import-download-errors').click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toContain('errored-rows');
    });
});

// Slimmed driveDonationsHappyPath that stops at the awaiting-decision phase so
// error-table assertions can fire before commit.
import type { Page } from '@playwright/test';
import { fillUploadStep, type NonContactWizardOptions } from '../helpers/wizard.js';

async function driveDonationsHappyPathUntilAwaiting(
    page: Page,
    opts: NonContactWizardOptions,
): Promise<void> {
    await page.goto('/admin/import-donations-page');
    await expect(page.getByTestId('import-donations-wizard')).toBeVisible();

    await fillUploadStep(page, opts);

    if (opts.contactMissingStrategy) {
        await page
            .locator(`input[type="radio"][name="data.contact_missing_strategy"][value="${opts.contactMissingStrategy}"]`)
            .check();
    }

    await page.getByTestId('import-step-next-0').click();
    await expect(page.getByTestId('import-step-next-1')).toBeVisible();

    await page.getByTestId('import-step-next-1').click();
    await expect(page.getByTestId('import-contact-match-key')).toBeVisible();

    await page.getByTestId('import-duplicate-strategy')
        .locator('input[type="radio"][value="skip"]')
        .check();

    await page.getByTestId('import-step-next-2').click();
    await expect(page.getByTestId('import-commit-button')).toBeVisible();

    await page.getByTestId('import-commit-button').click();
}

function insertAmbiguousContacts(): void {
    const sql = `
        INSERT INTO contacts (id, email, source, country, household_id, created_at, updated_at)
        SELECT gen_random_uuid(), email, 'manual', 'US', NULL, NOW(), NOW()
        FROM (VALUES
            ('ambig1@example.com'),
            ('ambig1@example.com'),
            ('ambig2@example.com'),
            ('ambig2@example.com'),
            ('ambig3@example.com'),
            ('ambig3@example.com'),
            ('ambig4@example.com'),
            ('ambig4@example.com'),
            ('ambig5@example.com'),
            ('ambig5@example.com')
        ) AS t(email);
    `;
    execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'postgres', 'psql', '-U', process.env.DB_USERNAME ?? 'nonprofitcrm', '-d', process.env.DB_DATABASE ?? 'nonprofitcrm', '-c', sql],
        { cwd: PROJECT_ROOT, stdio: 'inherit' },
    );
}
