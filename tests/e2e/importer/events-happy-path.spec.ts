import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import {
    cleanupAllImportSessionsOfType,
    countEventsInSession,
    countEventRegistrationsInSession,
    countTransactionsInSession,
    countStagedUpdatesForSession,
    findLatestImportSessionId,
    insertContactsFromCsv,
} from '../helpers/db.js';
import { resetAndLogin } from '../helpers/auth.js';
import { driveEventsHappyPath } from '../helpers/wizard.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const FIXTURE_DIR = path.resolve(__dirname, '../fixtures/events');
const HAPPY_PATH_CSV = path.join(FIXTURE_DIR, 'happy-path.csv');
const PRE_CREATED_CONTACTS_CSV = path.join(FIXTURE_DIR, 'happy-path-contacts.csv');
const EXPECTED = JSON.parse(fs.readFileSync(path.join(FIXTURE_DIR, 'happy-path.expected.json'), 'utf8'));

test.describe.configure({ mode: 'serial' });

test.describe('Events importer — happy path', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        await insertContactsFromCsv(PRE_CREATED_CONTACTS_CSV);
    });

    test.afterAll(async () => {
        await cleanupAllImportSessionsOfType('event');
    });

    test('imports 3 events with registrations and transactions', async ({ page }) => {
        test.setTimeout(180_000);

        await driveEventsHappyPath(page, {
            sessionLabel: 'E2E Events Happy Path',
            sourceName: 'E2E Events Source',
            csvPath: HAPPY_PATH_CSV,
        });

        await expect(page.getByTestId('import-stat-imported')).toContainText('3');
        await expect(page.getByTestId('import-stat-errors')).toContainText('0');

        const sessionId = await findLatestImportSessionId('event');
        expect(sessionId).not.toBeNull();
        expect(await countEventsInSession(sessionId!)).toBe(EXPECTED.eventCount);
        expect(await countEventRegistrationsInSession(sessionId!)).toBe(EXPECTED.registrationCount);
        expect(await countTransactionsInSession(sessionId!)).toBe(EXPECTED.transactionCount);
        expect(await countStagedUpdatesForSession(sessionId!)).toBe(0);

        await page.goto('/admin/events');
        for (const title of EXPECTED.eventTitles) {
            await expect(page.locator('.fi-ta-table')).toContainText(title);
        }
    });
});
