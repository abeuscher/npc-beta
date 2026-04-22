import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import {
    cleanupAllImportSessionsOfType,
    countStagedUpdatesForSession,
    findImportSourceIdByName,
    findLatestImportSessionId,
    findMembershipByExternalId,
} from '../helpers/db.js';
import { resetAndLogin } from '../helpers/auth.js';
import { approveSessionViaImporter, driveMembershipsHappyPath } from '../helpers/wizard.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const FIXTURE_DIR = path.resolve(__dirname, '../fixtures/memberships');
const HAPPY_PATH_CSV = path.join(FIXTURE_DIR, 'happy-path.csv');
const UPDATE_CSV = path.join(FIXTURE_DIR, 'update-second-pass.csv');
const EXPECTED = JSON.parse(fs.readFileSync(path.join(FIXTURE_DIR, 'happy-path.expected.json'), 'utf8'));

const SOURCE_NAME = 'E2E Memberships Update Source';

test.describe.configure({ mode: 'serial' });

test.describe('Memberships importer — update strategy', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test.afterAll(async () => {
        await cleanupAllImportSessionsOfType('membership');
    });

    test('re-import with update strategy stages notes changes; approval applies them', async ({ page }) => {
        test.setTimeout(300_000);

        await driveMembershipsHappyPath(page, {
            sessionLabel: 'Memberships seed pass',
            sourceName: SOURCE_NAME,
            csvPath: HAPPY_PATH_CSV,
            contactMissingStrategy: 'auto_create',
            extraColumnMappings: { [EXPECTED.externalIdColumnIndex]: 'membership:external_id' },
        });

        const firstSessionId = await findLatestImportSessionId('membership');
        expect(firstSessionId).not.toBeNull();
        await approveSessionViaImporter(page, firstSessionId!);

        const sourceId = await findImportSourceIdByName(SOURCE_NAME);
        expect(sourceId).not.toBeNull();

        await driveMembershipsHappyPath(page, {
            sessionLabel: 'Memberships update pass',
            sourceName_reuse: SOURCE_NAME,
            csvPath: UPDATE_CSV,
            duplicateStrategy: 'update',
            contactMissingStrategy: 'auto_create',
            extraColumnMappings: { [EXPECTED.externalIdColumnIndex]: 'membership:external_id' },
        });

        await expect(page.getByTestId('import-stat-updated')).toContainText('5');
        await expect(page.getByTestId('import-stat-errors')).toContainText('0');

        const secondSessionId = await findLatestImportSessionId('membership');
        expect(secondSessionId).not.toBeNull();
        expect(secondSessionId).not.toBe(firstSessionId);
        expect(await countStagedUpdatesForSession(secondSessionId!)).toBe(5);

        const firstMembership = await findMembershipByExternalId(sourceId!, 'MEM-E2E-1');
        expect(firstMembership).not.toBeNull();
        expect(firstMembership?.notes).toBe(EXPECTED.initialNotes['MEM-E2E-1']);

        await approveSessionViaImporter(page, secondSessionId!);

        expect(await countStagedUpdatesForSession(secondSessionId!)).toBe(0);

        for (const [externalId, expectedNotes] of Object.entries(EXPECTED.updatedNotes) as Array<[string, string]>) {
            const row = await findMembershipByExternalId(sourceId!, externalId);
            expect(row, `membership ${externalId}`).not.toBeNull();
            expect(row?.notes).toBe(expectedNotes);
        }
    });
});
