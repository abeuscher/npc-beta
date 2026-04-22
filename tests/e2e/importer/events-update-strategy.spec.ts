import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import {
    cleanupAllImportSessionsOfType,
    countStagedUpdatesForSession,
    findLatestImportSessionId,
    insertContactsFromCsv,
} from '../helpers/db.js';
import { resetAndLogin } from '../helpers/auth.js';
import { approveSessionViaImporter, driveEventsHappyPath } from '../helpers/wizard.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const FIXTURE_DIR = path.resolve(__dirname, '../fixtures/events');
const HAPPY_PATH_CSV = path.join(FIXTURE_DIR, 'happy-path.csv');
const UPDATE_CSV = path.join(FIXTURE_DIR, 'update-second-pass.csv');
const PRE_CREATED_CONTACTS_CSV = path.join(FIXTURE_DIR, 'happy-path-contacts.csv');
const EXPECTED = JSON.parse(fs.readFileSync(path.join(FIXTURE_DIR, 'happy-path.expected.json'), 'utf8'));

test.describe.configure({ mode: 'serial' });

test.describe('Events importer — update strategy', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        await insertContactsFromCsv(PRE_CREATED_CONTACTS_CSV);
    });

    test.afterAll(async () => {
        await cleanupAllImportSessionsOfType('event');
    });

    test('re-import with update strategy stages title changes; approval applies them', async ({ page }) => {
        test.setTimeout(300_000);

        await driveEventsHappyPath(page, {
            sessionLabel: 'Events seed pass',
            sourceName: 'E2E Events Update Source',
            csvPath: HAPPY_PATH_CSV,
        });

        const firstSessionId = await findLatestImportSessionId('event');
        expect(firstSessionId).not.toBeNull();
        await approveSessionViaImporter(page, firstSessionId!);

        await driveEventsHappyPath(page, {
            sessionLabel: 'Events update pass',
            sourceName_reuse: 'E2E Events Update Source',
            csvPath: UPDATE_CSV,
            duplicateStrategy: 'update',
        });

        await expect(page.getByTestId('import-stat-updated')).toContainText('3');
        await expect(page.getByTestId('import-stat-errors')).toContainText('0');

        const secondSessionId = await findLatestImportSessionId('event');
        expect(secondSessionId).not.toBeNull();
        expect(secondSessionId).not.toBe(firstSessionId);
        expect(await countStagedUpdatesForSession(secondSessionId!)).toBe(3);

        await approveSessionViaImporter(page, secondSessionId!);

        expect(await countStagedUpdatesForSession(secondSessionId!)).toBe(0);

        await page.goto('/admin/events');
        for (const newTitle of Object.values(EXPECTED.updatedTitles) as string[]) {
            await expect(page.locator('.fi-ta-table')).toContainText(newTitle);
        }
    });
});
