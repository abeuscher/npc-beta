import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import {
    cleanupAllImportSessionsOfType,
    countStagedUpdatesForSession,
    findDonationByExternalId,
    findImportSourceIdByName,
    findLatestImportSessionId,
} from '../helpers/db.js';
import { resetAndLogin } from '../helpers/auth.js';
import { approveSessionViaImporter, driveDonationsHappyPath } from '../helpers/wizard.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const FIXTURE_DIR = path.resolve(__dirname, '../fixtures/donations');
const HAPPY_PATH_CSV = path.join(FIXTURE_DIR, 'happy-path.csv');
const UPDATE_CSV = path.join(FIXTURE_DIR, 'update-second-pass.csv');
const EXPECTED = JSON.parse(fs.readFileSync(path.join(FIXTURE_DIR, 'happy-path.expected.json'), 'utf8'));

const SOURCE_NAME = 'E2E Donations Update Source';

test.describe.configure({ mode: 'serial' });

test.describe('Donations importer — update strategy', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test.afterAll(async () => {
        await cleanupAllImportSessionsOfType('donation');
    });

    test('re-import with update strategy stages amount changes; approval applies them', async ({ page }) => {
        test.setTimeout(300_000);

        await driveDonationsHappyPath(page, {
            sessionLabel: 'Donations seed pass',
            sourceName: SOURCE_NAME,
            csvPath: HAPPY_PATH_CSV,
            contactMissingStrategy: 'auto_create',
            extraColumnMappings: { [EXPECTED.externalIdColumnIndex]: 'donation:external_id' },
        });

        const firstSessionId = await findLatestImportSessionId('donation');
        expect(firstSessionId).not.toBeNull();
        await approveSessionViaImporter(page, firstSessionId!);

        const sourceId = await findImportSourceIdByName(SOURCE_NAME);
        expect(sourceId).not.toBeNull();

        await driveDonationsHappyPath(page, {
            sessionLabel: 'Donations update pass',
            sourceName_reuse: SOURCE_NAME,
            csvPath: UPDATE_CSV,
            duplicateStrategy: 'update',
            contactMissingStrategy: 'auto_create',
            extraColumnMappings: { [EXPECTED.externalIdColumnIndex]: 'donation:external_id' },
        });

        await expect(page.getByTestId('import-stat-updated')).toContainText('5');
        await expect(page.getByTestId('import-stat-errors')).toContainText('0');

        const secondSessionId = await findLatestImportSessionId('donation');
        expect(secondSessionId).not.toBeNull();
        expect(secondSessionId).not.toBe(firstSessionId);
        expect(await countStagedUpdatesForSession(secondSessionId!)).toBe(5);

        const firstDonation = await findDonationByExternalId(sourceId!, 'DON-E2E-1');
        expect(firstDonation).not.toBeNull();
        expect(Number(firstDonation?.amount)).toBe(Number(EXPECTED.initialAmounts['DON-E2E-1']));

        await approveSessionViaImporter(page, secondSessionId!);

        expect(await countStagedUpdatesForSession(secondSessionId!)).toBe(0);

        for (const [externalId, expectedAmount] of Object.entries(EXPECTED.updatedAmounts) as Array<[string, string]>) {
            const row = await findDonationByExternalId(sourceId!, externalId);
            expect(row, `donation ${externalId}`).not.toBeNull();
            expect(Number(row?.amount)).toBe(Number(expectedAmount));
        }
    });
});
