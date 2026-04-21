import { test, expect } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import {
    cleanCsvPath,
    FAKE_SOURCE_LABELS,
    parseCsv,
    primeFakeFixtures,
} from '../helpers/fake-csv.js';
import { driveNotesHappyPath } from '../helpers/wizard.js';
import {
    countNotesInSession,
    findLatestImportSessionId,
    findNoteByExternalId,
    findImportSourceIdByName,
    insertContactsForImport,
} from '../helpers/db.js';

test.describe.configure({ mode: 'serial' });

test.describe('Notes importer — happy path', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        primeFakeFixtures(4206);

        // Notes import looks up contacts by email and errors on miss.
        // Seed contacts directly from the generated contacts.csv so every
        // note row resolves without running the full contacts wizard.
        const contacts = parseCsv(cleanCsvPath('contacts.csv'));
        const emailIdx = contacts.headers.indexOf('Email');
        expect(emailIdx).toBeGreaterThanOrEqual(0);

        const emails = contacts.rows
            .map((r) => r[emailIdx])
            .filter((e): e is string => typeof e === 'string' && e !== '');

        await insertContactsForImport(emails);
    });

    test('imports every note from the generated notes.csv and attaches each to its contact', async ({ page }) => {
        test.setTimeout(300_000);

        const notes = parseCsv(cleanCsvPath('notes.csv'));
        const expectedRowCount = notes.rows.length;
        expect(expectedRowCount).toBeGreaterThan(0);

        const externalIdIdx = notes.headers.indexOf('Note External ID');
        expect(externalIdIdx).toBeGreaterThanOrEqual(0);
        const firstExternalId = notes.rows[0][externalIdIdx];

        await driveNotesHappyPath(page, {
            sessionLabel: 'E2E Notes Happy Path',
            sourceName_reuse: FAKE_SOURCE_LABELS.notes,
            csvPath: cleanCsvPath('notes.csv'),
        });

        await expect(page.getByTestId('import-stat-imported')).toContainText(String(expectedRowCount));
        await expect(page.getByTestId('import-stat-errors')).toContainText('0');

        const sessionId = await findLatestImportSessionId('note');
        expect(sessionId).not.toBeNull();
        expect(await countNotesInSession(sessionId!)).toBe(expectedRowCount);

        const sourceId = await findImportSourceIdByName(FAKE_SOURCE_LABELS.notes);
        expect(sourceId).not.toBeNull();

        const firstNote = await findNoteByExternalId(sourceId!, firstExternalId);
        expect(firstNote).not.toBeNull();
        expect(firstNote!.external_id).toBe(firstExternalId);
    });
});
