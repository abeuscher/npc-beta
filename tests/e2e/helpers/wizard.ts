import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

export type WizardOptions = {
    sessionLabel: string;
    sourceName?: string;
    sourceName_reuse?: string;
    csvPath: string;
    duplicateStrategy?: 'skip' | 'update' | 'duplicate';
};

export async function openContactsWizard(page: Page): Promise<void> {
    await page.goto('/admin/import-contacts-page');
    await expect(page.getByTestId('import-contacts-wizard')).toBeVisible();
}

export async function fillUploadStep(page: Page, opts: WizardOptions): Promise<void> {
    await page.locator('[id="data.session_label"]').fill(opts.sessionLabel);

    if (opts.sourceName) {
        await page.locator('[id="data.import_source_name"]').fill(opts.sourceName);
    } else if (opts.sourceName_reuse) {
        await selectExistingSource(page, opts.sourceName_reuse);
    }

    const fileInput = page.locator('input[type=file]');
    await fileInput.setInputFiles(opts.csvPath);

    await expect(page.locator('p', { hasText: 'Uploading file' })).toBeHidden({ timeout: 30_000 });
    await expect(page.locator('.filepond--file-status-main', { hasText: 'Upload complete' })).toBeVisible({ timeout: 30_000 });
    await page.waitForLoadState('networkidle');
}

async function selectExistingSource(page: Page, sourceName: string): Promise<void> {
    const selectEl = page.getByTestId('import-source-select').locator('select');
    await selectEl.selectOption({ label: sourceName });
}

export async function advanceThroughMapping(page: Page, opts: WizardOptions): Promise<void> {
    await page.getByTestId('import-step-next-0').click();
    await expect(page.getByTestId('import-step-next-1')).toBeVisible();

    await page.getByTestId('import-step-next-1').click();
    await expect(page.getByTestId('import-match-key')).toBeVisible();

    const strategy = opts.duplicateStrategy ?? 'skip';
    await page.getByTestId('import-duplicate-strategy')
        .locator(`input[type="radio"][value="${strategy}"]`)
        .check();

    await page.getByTestId('import-step-next-2').click();
    await expect(page.getByTestId('import-commit-button')).toBeVisible();
}

export async function stageAndCommit(page: Page): Promise<void> {
    await page.getByTestId('import-commit-button').click();

    await expect(page.getByTestId('import-progress-phase-awaiting')).toBeVisible({ timeout: 30_000 });

    await page.getByTestId('import-progress-commit-button').click();

    await expect(page.getByTestId('import-progress-phase-done')).toBeVisible({ timeout: 120_000 });
}

export async function driveHappyPathImport(page: Page, opts: WizardOptions): Promise<void> {
    await openContactsWizard(page);
    await fillUploadStep(page, opts);
    await advanceThroughMapping(page, opts);
    await stageAndCommit(page);
}

export async function approveSessionViaImporter(page: Page, sessionId: string): Promise<void> {
    await page.goto('/admin/importer-page');
    const approve = page.getByTestId(`importer-action-approve-${sessionId}`);
    await expect(approve).toBeVisible();
    await approve.click();
    await page.getByTestId('importer-modal-approve-submit').click();
    await expect(page.getByTestId(`importer-action-approve-${sessionId}`)).toHaveCount(0);
}
