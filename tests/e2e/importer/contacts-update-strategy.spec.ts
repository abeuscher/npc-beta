import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { cleanupAllImportSessionsOfType, countStagedUpdatesForSession, findContactByEmail, findLatestImportSessionId } from '../helpers/db.js';
import { resetAndLogin } from '../helpers/auth.js';
import { approveSessionViaImporter, driveHappyPathImport } from '../helpers/wizard.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const FIXTURE_DIR = path.resolve(__dirname, '../fixtures/contacts');
const HAPPY_PATH_CSV = path.join(FIXTURE_DIR, 'happy-path.csv');
const UPDATE_CSV = path.join(FIXTURE_DIR, 'update-second-pass.csv');
const EXPECTED = JSON.parse(fs.readFileSync(path.join(FIXTURE_DIR, 'happy-path.expected.json'), 'utf8'));

test.describe.configure({ mode: 'serial' });

test.describe('Contacts importer — update strategy', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test.afterAll(async () => {
        await cleanupAllImportSessionsOfType('contact');
    });

    test('re-import with update strategy stages 5 changes and approve applies them', async ({ page }) => {
        test.setTimeout(300_000);

        await driveHappyPathImport(page, {
            sessionLabel: 'Seed pass',
            sourceName: 'E2E Update Source',
            csvPath: HAPPY_PATH_CSV,
        });

        const firstSessionId = await findLatestImportSessionId();
        expect(firstSessionId).not.toBeNull();
        await approveSessionViaImporter(page, firstSessionId!);

        await driveHappyPathImport(page, {
            sessionLabel: 'Update pass',
            sourceName_reuse: 'E2E Update Source',
            csvPath: UPDATE_CSV,
            duplicateStrategy: 'update',
        });

        await expect(page.getByTestId('import-stat-updated')).toContainText('5');
        await expect(page.getByTestId('import-stat-imported')).toContainText('0');
        await expect(page.getByTestId('import-stat-errors')).toContainText('0');

        const secondSessionId = await findLatestImportSessionId();
        expect(secondSessionId).not.toBeNull();
        expect(secondSessionId).not.toBe(firstSessionId);
        expect(await countStagedUpdatesForSession(secondSessionId!)).toBe(5);

        await page.goto('/admin/importer-page');
        const preview = page.getByTestId(`importer-action-preview-${secondSessionId}`);
        await expect(preview).toBeVisible();
        await preview.click();
        await expect(page.locator('text=Staged updates to existing contacts')).toBeVisible();
        await page.locator('.fi-modal').getByRole('button', { name: 'Close' }).last().click();

        await approveSessionViaImporter(page, secondSessionId!);

        expect(await countStagedUpdatesForSession(secondSessionId!)).toBe(0);

        for (const [email, expectedFields] of Object.entries(EXPECTED.updatedSpotChecks) as Array<[string, Record<string, string>]>) {
            const row = await findContactByEmail(email);
            expect(row, `contact ${email} should still exist after approval`).not.toBeNull();

            for (const [field, value] of Object.entries(expectedFields)) {
                expect(row?.[field as keyof typeof row], `${email}.${field}`).toBe(value);
            }
        }
    });
});
