import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import {
    cleanupAllImportSessionsOfType,
    countMembershipsInSession,
    countStagedUpdatesForSession,
    findLatestImportSessionId,
} from '../helpers/db.js';
import { resetAndLogin } from '../helpers/auth.js';
import { driveMembershipsHappyPath } from '../helpers/wizard.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const FIXTURE_DIR = path.resolve(__dirname, '../fixtures/memberships');
const HAPPY_PATH_CSV = path.join(FIXTURE_DIR, 'happy-path.csv');
const EXPECTED = JSON.parse(fs.readFileSync(path.join(FIXTURE_DIR, 'happy-path.expected.json'), 'utf8'));

test.describe.configure({ mode: 'serial' });

test.describe('Memberships importer — happy path', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test.afterAll(async () => {
        await cleanupAllImportSessionsOfType('membership');
    });

    test('imports 5 memberships and auto-creates contacts', async ({ page }) => {
        test.setTimeout(180_000);

        await driveMembershipsHappyPath(page, {
            sessionLabel: 'E2E Memberships Happy Path',
            sourceName: 'E2E Memberships Source',
            csvPath: HAPPY_PATH_CSV,
            contactMissingStrategy: 'auto_create',
            extraColumnMappings: { [EXPECTED.externalIdColumnIndex]: 'membership:external_id' },
        });

        await expect(page.getByTestId('import-stat-imported')).toContainText('5');
        await expect(page.getByTestId('import-stat-errors')).toContainText('0');

        const sessionId = await findLatestImportSessionId('membership');
        expect(sessionId).not.toBeNull();
        expect(await countMembershipsInSession(sessionId!)).toBe(EXPECTED.membershipCount);
        expect(await countStagedUpdatesForSession(sessionId!)).toBe(0);
    });
});
