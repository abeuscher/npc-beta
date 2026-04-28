import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import {
    cleanupAllImportSessionsOfType,
    countDonationsInSession,
    countDonationsInSessionByStatus,
    countTransactionsInSession,
    countStagedUpdatesForSession,
    findLatestImportSessionId,
} from '../helpers/db.js';
import { resetAndLogin } from '../helpers/auth.js';
import { driveDonationsHappyPath } from '../helpers/wizard.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const FIXTURE_DIR = path.resolve(__dirname, '../fixtures/donations');
const HAPPY_PATH_CSV = path.join(FIXTURE_DIR, 'happy-path.csv');
const EXPECTED = JSON.parse(fs.readFileSync(path.join(FIXTURE_DIR, 'happy-path.expected.json'), 'utf8'));

test.describe.configure({ mode: 'serial' });

test.describe('Donations importer — happy path', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test.afterAll(async () => {
        await cleanupAllImportSessionsOfType('donation');
    });

    test('imports 5 donations with transactions and auto-creates contacts', async ({ page }) => {
        test.setTimeout(180_000);

        await driveDonationsHappyPath(page, {
            sessionLabel: 'E2E Donations Happy Path',
            sourceName: 'E2E Donations Source',
            csvPath: HAPPY_PATH_CSV,
            contactMissingStrategy: 'auto_create',
            extraColumnMappings: { [EXPECTED.externalIdColumnIndex]: 'donation:external_id' },
        });

        await expect(page.getByTestId('import-stat-imported')).toContainText('5');
        await expect(page.getByTestId('import-stat-errors')).toContainText('0');

        const sessionId = await findLatestImportSessionId('donation');
        expect(sessionId).not.toBeNull();
        expect(await countDonationsInSession(sessionId!)).toBe(EXPECTED.donationCount);
        expect(await countDonationsInSessionByStatus(sessionId!, 'active')).toBe(EXPECTED.donationCount);
        expect(await countTransactionsInSession(sessionId!)).toBe(EXPECTED.transactionCount);
        expect(await countStagedUpdatesForSession(sessionId!)).toBe(0);
    });
});
