import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { countContactsInSession, countStagedUpdatesForSession, findContactByEmail, findLatestImportSessionId } from '../helpers/db.js';
import { resetAndLogin } from '../helpers/auth.js';
import { driveHappyPathImport } from '../helpers/wizard.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const FIXTURE_DIR = path.resolve(__dirname, '../fixtures/contacts');
const HAPPY_PATH_CSV = path.join(FIXTURE_DIR, 'happy-path.csv');
const EXPECTED = JSON.parse(fs.readFileSync(path.join(FIXTURE_DIR, 'happy-path.expected.json'), 'utf8'));

test.describe.configure({ mode: 'serial' });

test.describe('Contacts importer — happy path', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test('imports 5 contacts end-to-end and lands in pending-review', async ({ page }) => {
        test.setTimeout(180_000);

        await driveHappyPathImport(page, {
            sessionLabel: 'E2E Happy Path',
            sourceName: 'E2E Source',
            csvPath: HAPPY_PATH_CSV,
        });

        await expect(page.getByTestId('import-stat-imported')).toContainText('5');
        await expect(page.getByTestId('import-stat-errors')).toContainText('0');

        const sessionId = await findLatestImportSessionId();
        expect(sessionId).not.toBeNull();
        expect(await countContactsInSession(sessionId!)).toBe(EXPECTED.contactCount);
        expect(await countStagedUpdatesForSession(sessionId!)).toBe(0);

        for (const email of EXPECTED.emails) {
            const row = await findContactByEmail(email);
            expect(row, `contact ${email} should exist`).not.toBeNull();
        }

        const alice = await findContactByEmail('alice.anderson@example.com');
        const spot = EXPECTED.spotChecks['alice.anderson@example.com'];
        expect(alice?.first_name).toBe(spot.first_name);
        expect(alice?.last_name).toBe(spot.last_name);
        expect(alice?.phone).toBe(spot.phone);
        expect(alice?.city).toBe(spot.city);
        expect(alice?.state).toBe(spot.state);

        await page.goto('/admin/contacts');
        await expect(page.locator('.fi-ta-table')).toContainText('alice.anderson@example.com');
        await expect(page.locator('.fi-ta-table')).toContainText('eva.evans@example.com');
    });
});
