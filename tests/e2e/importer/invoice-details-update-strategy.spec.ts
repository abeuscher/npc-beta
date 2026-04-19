import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import {
    countStagedUpdatesForSession,
    findLatestImportSessionId,
    findTransactionByInvoiceNumber,
} from '../helpers/db.js';
import { resetAndLogin } from '../helpers/auth.js';
import { approveSessionViaImporter, driveInvoiceDetailsHappyPath } from '../helpers/wizard.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const FIXTURE_DIR = path.resolve(__dirname, '../fixtures/invoice-details');
const HAPPY_PATH_CSV = path.join(FIXTURE_DIR, 'happy-path.csv');
const UPDATE_CSV = path.join(FIXTURE_DIR, 'update-second-pass.csv');
const EXPECTED = JSON.parse(fs.readFileSync(path.join(FIXTURE_DIR, 'happy-path.expected.json'), 'utf8'));

test.describe.configure({ mode: 'serial' });

test.describe('Invoice Details importer — update strategy', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test('re-import with update strategy stages line-item changes; approval applies them', async ({ page }) => {
        test.setTimeout(300_000);

        await driveInvoiceDetailsHappyPath(page, {
            sessionLabel: 'Invoice Details seed pass',
            sourceName: 'E2E Invoice Details Update Source',
            csvPath: HAPPY_PATH_CSV,
            contactMissingStrategy: 'auto_create',
        });

        const firstSessionId = await findLatestImportSessionId('invoice_detail');
        expect(firstSessionId).not.toBeNull();
        await approveSessionViaImporter(page, firstSessionId!);

        await driveInvoiceDetailsHappyPath(page, {
            sessionLabel: 'Invoice Details update pass',
            sourceName_reuse: 'E2E Invoice Details Update Source',
            csvPath: UPDATE_CSV,
            duplicateStrategy: 'update',
            contactMissingStrategy: 'auto_create',
        });

        await expect(page.getByTestId('import-stat-updated')).toContainText(/[1-9]/);
        await expect(page.getByTestId('import-stat-errors')).toContainText('0');

        const secondSessionId = await findLatestImportSessionId('invoice_detail');
        expect(secondSessionId).not.toBeNull();
        expect(secondSessionId).not.toBe(firstSessionId);

        const stagedCount = await countStagedUpdatesForSession(secondSessionId!);
        expect(stagedCount).toBeGreaterThan(0);

        const txBefore = await findTransactionByInvoiceNumber('INV-E2E-B');
        expect(txBefore).not.toBeNull();
        const lineItemsBefore = (txBefore?.line_items as Array<{ amount: string | number }> | null) ?? [];
        const amountsBefore = lineItemsBefore.map((item) => Number(item.amount));
        const expectedBefore = (EXPECTED.initialLineItemAmounts['INV-E2E-B'] as string[]).map(Number);
        expect(amountsBefore).toEqual(expect.arrayContaining(expectedBefore));

        await approveSessionViaImporter(page, secondSessionId!);

        expect(await countStagedUpdatesForSession(secondSessionId!)).toBe(0);

        const txAfter = await findTransactionByInvoiceNumber('INV-E2E-B');
        expect(txAfter).not.toBeNull();
        const lineItemsAfter = (txAfter?.line_items as Array<{ amount: string | number }> | null) ?? [];
        const amountsAfter = lineItemsAfter.map((item) => Number(item.amount));
        const expectedAfter = (EXPECTED.updatedLineItemAmounts['INV-E2E-B'] as string[]).map(Number);
        expect(amountsAfter).toEqual(expect.arrayContaining(expectedAfter));
    });
});
