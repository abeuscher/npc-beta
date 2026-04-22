import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import {
    cleanupAllImportSessionsOfType,
    countTransactionsInSession,
    countStagedUpdatesForSession,
    findLatestImportSessionId,
    findTransactionByInvoiceNumber,
} from '../helpers/db.js';
import { resetAndLogin } from '../helpers/auth.js';
import { driveInvoiceDetailsHappyPath } from '../helpers/wizard.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const FIXTURE_DIR = path.resolve(__dirname, '../fixtures/invoice-details');
const HAPPY_PATH_CSV = path.join(FIXTURE_DIR, 'happy-path.csv');
const EXPECTED = JSON.parse(fs.readFileSync(path.join(FIXTURE_DIR, 'happy-path.expected.json'), 'utf8'));

test.describe.configure({ mode: 'serial' });

test.describe('Invoice Details importer — happy path', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test.afterAll(async () => {
        await cleanupAllImportSessionsOfType('invoice_detail');
    });

    test('imports 6 rows into 3 transactions with grouped line items', async ({ page }) => {
        test.setTimeout(180_000);

        await driveInvoiceDetailsHappyPath(page, {
            sessionLabel: 'E2E Invoice Details Happy Path',
            sourceName: 'E2E Invoice Details Source',
            csvPath: HAPPY_PATH_CSV,
            contactMissingStrategy: 'auto_create',
        });

        await expect(page.getByTestId('import-stat-errors')).toContainText('0');

        const sessionId = await findLatestImportSessionId('invoice_detail');
        expect(sessionId).not.toBeNull();
        expect(await countTransactionsInSession(sessionId!)).toBe(EXPECTED.transactionCount);
        expect(await countStagedUpdatesForSession(sessionId!)).toBe(0);

        for (const invoiceNumber of EXPECTED.invoiceNumbers) {
            const tx = await findTransactionByInvoiceNumber(invoiceNumber);
            expect(tx, `transaction ${invoiceNumber}`).not.toBeNull();
        }
    });
});
