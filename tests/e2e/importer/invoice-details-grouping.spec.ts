import { test, expect } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import {
    cleanCsvPath,
    FAKE_SOURCE_LABELS,
    parseCsv,
    primeFakeFixtures,
    tempCsvPath,
    writeCsv,
} from '../helpers/fake-csv.js';
import { driveInvoiceDetailsHappyPath } from '../helpers/wizard.js';
import { findTransactionByInvoiceNumber } from '../helpers/db.js';

test.describe.configure({ mode: 'serial' });

test.describe('Invoice-details importer — line-item ordering under update strategy', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        primeFakeFixtures(4204);
    });

    test('line-item ordering is preserved for a 3+ line-item invoice group', async ({ page }) => {
        test.setTimeout(300_000);

        const clean = parseCsv(cleanCsvPath('invoice_details.csv'));
        const invoiceColIdx = clean.headers.indexOf('Invoice #');
        const itemColIdx    = clean.headers.indexOf('Item Description');

        const groups: Record<string, { descriptions: string[] }> = {};
        for (const r of clean.rows) {
            const num = r[invoiceColIdx];
            if (! groups[num]) groups[num] = { descriptions: [] };
            groups[num].descriptions.push(r[itemColIdx]);
        }
        const targetInvoice = Object.entries(groups).find(([_, g]) => g.descriptions.length >= 3);
        expect(targetInvoice, 'no invoice group with ≥3 line items in generated CSV').toBeDefined();
        const [targetNumber, targetGroup] = targetInvoice!;
        const expectedOrder = [...targetGroup.descriptions];

        await driveInvoiceDetailsHappyPath(page, {
            sessionLabel: 'E2E Invoice Ordering',
            sourceName_reuse: FAKE_SOURCE_LABELS.invoiceDetails,
            csvPath: cleanCsvPath('invoice_details.csv'),
            contactMissingStrategy: 'auto_create',
            duplicateStrategy: 'update',
        });

        const txn = await findTransactionByInvoiceNumber(targetNumber);
        expect(txn, `transaction for invoice ${targetNumber} not found`).not.toBeNull();
        const lineItems = (txn!.line_items as Array<Record<string, unknown>>) ?? [];
        const actualOrder = lineItems.map((li) => li.item as string);
        expect(actualOrder).toEqual(expectedOrder);
    });
});

test.describe('Invoice-details importer — parent-field conflict resolution', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        primeFakeFixtures(4205);
    });

    test('conflicting parent-invoice fields resolve via first-row-wins', async ({ page }) => {
        test.setTimeout(300_000);

        const clean = parseCsv(cleanCsvPath('invoice_details.csv'));
        const invoiceColIdx  = clean.headers.indexOf('Invoice #');
        const paymentTypeIdx = clean.headers.indexOf('Payment Type');

        const rowIndicesByInvoice: Record<string, number[]> = {};
        clean.rows.forEach((r, idx) => {
            const num = r[invoiceColIdx];
            if (! rowIndicesByInvoice[num]) rowIndicesByInvoice[num] = [];
            rowIndicesByInvoice[num].push(idx);
        });
        const targetEntry = Object.entries(rowIndicesByInvoice).find(([_, idxs]) => idxs.length >= 2);
        expect(targetEntry, 'no invoice group with ≥2 rows').toBeDefined();
        const [targetNumber, rowIdxs] = targetEntry!;

        const firstRowIdx  = rowIdxs[0];
        const secondRowIdx = rowIdxs[1];
        const winningPaymentType = clean.rows[firstRowIdx][paymentTypeIdx];
        const losingPaymentType  = winningPaymentType === 'Card' ? 'Cash' : 'Card';

        const mutatedRows = clean.rows.map((r) => [...r]);
        mutatedRows[secondRowIdx][paymentTypeIdx] = losingPaymentType;

        const tmp = tempCsvPath('invoice-details-conflict');
        writeCsv(tmp, clean.headers, mutatedRows);

        await driveInvoiceDetailsHappyPath(page, {
            sessionLabel: 'E2E Invoice Conflict',
            sourceName_reuse: FAKE_SOURCE_LABELS.invoiceDetails,
            csvPath: tmp,
            contactMissingStrategy: 'auto_create',
            duplicateStrategy: 'update',
        });

        const txn = await findTransactionByInvoiceNumber(targetNumber);
        expect(txn, `transaction for invoice ${targetNumber} not found`).not.toBeNull();
        expect(txn!.payment_method).toBe(winningPaymentType);
    });
});
