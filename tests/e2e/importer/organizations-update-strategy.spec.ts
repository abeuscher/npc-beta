import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import {
    cleanupAllImportSessionsOfType,
    countStagedUpdatesForSession,
    findImportSourceIdByName,
    findLatestImportSessionId,
    findOrganizationByExternalId,
    getTagsForOrganization,
} from '../helpers/db.js';
import { resetAndLogin } from '../helpers/auth.js';
import { approveSessionViaImporter, driveOrganizationsHappyPath } from '../helpers/wizard.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const FIXTURE_DIR = path.resolve(__dirname, '../fixtures/organizations');
const HAPPY_PATH_CSV = path.join(FIXTURE_DIR, 'happy-path.csv');
const UPDATE_CSV = path.join(FIXTURE_DIR, 'update-second-pass.csv');
const EXPECTED = JSON.parse(fs.readFileSync(path.join(FIXTURE_DIR, 'happy-path.expected.json'), 'utf8'));

const SOURCE_NAME = 'E2E Organizations Update Source';

test.describe.configure({ mode: 'serial' });

test.describe('Organizations importer — update strategy', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test.afterAll(async () => {
        await cleanupAllImportSessionsOfType('organization');
    });

    test('re-import with update strategy fills blanks; existing non-blank values preserved @on-demand', async ({ page }) => {
        test.setTimeout(300_000);

        // First pass — seed the orgs.
        await driveOrganizationsHappyPath(page, {
            sessionLabel: 'Organizations seed pass',
            sourceName: SOURCE_NAME,
            csvPath: HAPPY_PATH_CSV,
            extraColumnMappings: {
                [EXPECTED.columnIndexes.industry]: '__custom_organization__',
                [EXPECTED.columnIndexes.tags]:     '__tag_organization__',
                [EXPECTED.columnIndexes.notes]:    '__note_organization__',
            },
            tagDelimiters: {
                [EXPECTED.columnIndexes.tags]: '|',
            },
        });

        const firstSessionId = await findLatestImportSessionId('organization');
        expect(firstSessionId).not.toBeNull();
        await approveSessionViaImporter(page, firstSessionId!);

        const sourceId = await findImportSourceIdByName(SOURCE_NAME);
        expect(sourceId).not.toBeNull();

        // Sanity — the existing ACME row has its original website / email pre-update.
        const acmeBefore = await findOrganizationByExternalId(sourceId!, 'ORG-E2E-1');
        expect(acmeBefore?.website).toBe('https://acme.example');
        expect(acmeBefore?.email).toBe('info@acme.example');

        // Second pass — update strategy.
        await driveOrganizationsHappyPath(page, {
            sessionLabel: 'Organizations update pass',
            sourceName_reuse: SOURCE_NAME,
            csvPath: UPDATE_CSV,
            duplicateStrategy: 'update',
            extraColumnMappings: {
                [EXPECTED.columnIndexes.industry]: '__custom_organization__',
                [EXPECTED.columnIndexes.tags]:     '__tag_organization__',
                [EXPECTED.columnIndexes.notes]:    '__note_organization__',
            },
            tagDelimiters: {
                [EXPECTED.columnIndexes.tags]: '|',
            },
        });

        await expect(page.getByTestId('import-stat-updated')).toContainText('5');
        await expect(page.getByTestId('import-stat-errors')).toContainText('0');

        const secondSessionId = await findLatestImportSessionId('organization');
        expect(secondSessionId).not.toBeNull();
        expect(secondSessionId).not.toBe(firstSessionId);

        // Pre-approval — staged updates exist for orgs that have blanks to fill.
        // ACME has all attrs already; nothing should stage. The other 4 should each stage at least one attr.
        const stagedCount = await countStagedUpdatesForSession(secondSessionId!);
        expect(stagedCount).toBeGreaterThanOrEqual(3);

        // Pre-approval — staged values aren't applied yet.
        const acmeMid = await findOrganizationByExternalId(sourceId!, 'ORG-E2E-1');
        expect(acmeMid?.website).toBe('https://acme.example');
        expect(acmeMid?.email).toBe('info@acme.example');

        await approveSessionViaImporter(page, secondSessionId!);
        expect(await countStagedUpdatesForSession(secondSessionId!)).toBe(0);

        // Post-approval: ACME's website and email STAYED (existing non-blank preserved).
        const acmeAfter = await findOrganizationByExternalId(sourceId!, 'ORG-E2E-1');
        expect(acmeAfter?.website).toBe(EXPECTED.preservedOnUpdate['ACME Corp'].website);
        expect(acmeAfter?.email).toBe(EXPECTED.preservedOnUpdate['ACME Corp'].email);

        // Initech — email was blank, now filled.
        const initech = await findOrganizationByExternalId(sourceId!, 'ORG-E2E-3');
        expect(initech?.email).toBe(EXPECTED.filledOnUpdate['ORG-E2E-3'].email);

        // City of Sample — email was blank, now filled.
        const city = await findOrganizationByExternalId(sourceId!, 'ORG-E2E-4');
        expect(city?.email).toBe(EXPECTED.filledOnUpdate['ORG-E2E-4'].email);

        // Fictional — email and phone were blank, both now filled.
        const fictional = await findOrganizationByExternalId(sourceId!, 'ORG-E2E-5');
        expect(fictional?.email).toBe(EXPECTED.filledOnUpdate['ORG-E2E-5'].email);
        expect(fictional?.phone).toBe(EXPECTED.filledOnUpdate['ORG-E2E-5'].phone);

        // Tags — added during update pass (immediate, not staged). ACME had {sponsor, partner}; update-pass file says {gold, sponsor}.
        // syncWithoutDetaching adds 'gold'; existing 'partner' stays.
        const acmeTags = await getTagsForOrganization(acmeAfter!.id as string);
        expect([...acmeTags].sort()).toEqual(['gold', 'partner', 'sponsor']);
    });
});
