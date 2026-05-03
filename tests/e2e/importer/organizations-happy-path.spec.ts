import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import {
    cleanupAllImportSessionsOfType,
    countNotesByNotableType,
    countOrganizationsInSession,
    countTagsByType,
    findImportSourceIdByName,
    findLatestImportSessionId,
    findOrganizationByExternalId,
    getNotesForOrganization,
    getTagsForOrganization,
} from '../helpers/db.js';
import { resetAndLogin } from '../helpers/auth.js';
import { driveOrganizationsHappyPath } from '../helpers/wizard.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const FIXTURE_DIR = path.resolve(__dirname, '../fixtures/organizations');
const HAPPY_PATH_CSV = path.join(FIXTURE_DIR, 'happy-path.csv');
const EXPECTED = JSON.parse(fs.readFileSync(path.join(FIXTURE_DIR, 'happy-path.expected.json'), 'utf8'));

const SOURCE_NAME = 'E2E Organizations Source';

test.describe.configure({ mode: 'serial' });

test.describe('Organizations importer — happy path', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test.afterAll(async () => {
        await cleanupAllImportSessionsOfType('organization');
    });

    test('imports 5 organizations with custom field, tags, and notes @on-demand', async ({ page }) => {
        test.setTimeout(180_000);

        await driveOrganizationsHappyPath(page, {
            sessionLabel: 'E2E Organizations Happy Path',
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

        await expect(page.getByTestId('import-stat-imported')).toContainText('5');
        await expect(page.getByTestId('import-stat-errors')).toContainText('0');

        const sessionId = await findLatestImportSessionId('organization');
        expect(sessionId).not.toBeNull();
        expect(await countOrganizationsInSession(sessionId!)).toBe(EXPECTED.organizationCount);

        const sourceId = await findImportSourceIdByName(SOURCE_NAME);
        expect(sourceId).not.toBeNull();

        // Core fields persisted via Org importer (source = import).
        const acme = await findOrganizationByExternalId(sourceId!, 'ORG-E2E-1');
        expect(acme).not.toBeNull();
        expect(acme?.name).toBe('ACME Corp');
        expect(acme?.type).toBe('for_profit');
        expect(acme?.website).toBe('https://acme.example');
        expect(acme?.email).toBe('info@acme.example');
        expect(acme?.phone).toBe('555-0100');
        expect(acme?.source).toBe('import');
        expect(acme?.import_source_id).toBe(sourceId);
        expect(acme?.import_session_id).toBe(sessionId);

        // Custom field round-trip — Industry → custom_fields jsonb.
        for (const [externalId, industry] of Object.entries(EXPECTED.industryByExternalId) as Array<[string, string]>) {
            const row = await findOrganizationByExternalId(sourceId!, externalId);
            expect(row, `org ${externalId}`).not.toBeNull();
            expect(row?.custom_fields).toEqual({ industry });
        }

        // Tag round-trip — tag rows created with type=organization, morphed onto the org.
        expect(await countTagsByType('organization')).toBe(EXPECTED.tagCount);

        const acmeTags = await getTagsForOrganization(acme!.id as string);
        expect([...acmeTags].sort()).toEqual(['partner', 'sponsor']);

        const initech = await findOrganizationByExternalId(sourceId!, 'ORG-E2E-3');
        const initechTags = await getTagsForOrganization(initech!.id as string);
        expect(initechTags).toEqual(['sponsor']);

        const fictional = await findOrganizationByExternalId(sourceId!, 'ORG-E2E-5');
        const fictionalTags = await getTagsForOrganization(fictional!.id as string);
        expect(fictionalTags).toEqual([]);

        // Note round-trip — Note rows with notable_type=Organization.
        const orgNoteCount = await countNotesByNotableType('App\\Models\\Organization');
        expect(orgNoteCount).toBe(Object.keys(EXPECTED.notesByExternalId).length);

        for (const [externalId, expectedBody] of Object.entries(EXPECTED.notesByExternalId) as Array<[string, string]>) {
            const row = await findOrganizationByExternalId(sourceId!, externalId);
            const notes = await getNotesForOrganization(row!.id as string);
            expect(notes.length, `org ${externalId} note count`).toBe(1);
            expect(notes[0].body).toBe(expectedBody);
        }

        // Org without a note — Initech CSV row 3 — has empty Notes cell.
        const initechNotes = await getNotesForOrganization(initech!.id as string);
        expect(initechNotes).toEqual([]);
    });
});
