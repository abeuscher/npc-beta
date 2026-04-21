import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

export type WizardOptions = {
    sessionLabel: string;
    sourceName?: string;
    sourceName_reuse?: string;
    csvPath: string;
    duplicateStrategy?: 'skip' | 'update' | 'duplicate';
};

export type NonContactWizardOptions = WizardOptions & {
    contactMissingStrategy?: 'error' | 'auto_create';
    extraColumnMappings?: Record<number, string>;
};

type ImporterKey = 'events' | 'donations' | 'memberships' | 'invoice-details' | 'notes';

const WIZARD_URL: Record<ImporterKey, string> = {
    'events': '/admin/import-events-page',
    'donations': '/admin/import-donations-page',
    'memberships': '/admin/import-memberships-page',
    'invoice-details': '/admin/import-invoice-details-page',
    'notes': '/admin/import-notes-page',
};

const WIZARD_TESTID: Record<ImporterKey, string> = {
    'events': 'import-events-wizard',
    'donations': 'import-donations-wizard',
    'memberships': 'import-memberships-wizard',
    'invoice-details': 'import-invoice-details-wizard',
    'notes': 'import-notes-wizard',
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

async function openNonContactWizard(page: Page, key: ImporterKey): Promise<void> {
    await page.goto(WIZARD_URL[key]);
    await expect(page.getByTestId(WIZARD_TESTID[key])).toBeVisible();
}

async function applyContactMissingStrategy(page: Page, strategy: 'error' | 'auto_create'): Promise<void> {
    await page
        .locator(`input[type="radio"][name="data.contact_missing_strategy"][value="${strategy}"]`)
        .check();
}

async function applyExtraColumnMappings(page: Page, mappings: Record<number, string>): Promise<void> {
    for (const [colIdx, destination] of Object.entries(mappings)) {
        const selectEl = page.getByTestId(`map-column-${colIdx}`).locator('select');
        await selectEl.selectOption(destination);
        await page.waitForLoadState('networkidle');
    }
}

async function advanceNonContactMapping(page: Page, opts: NonContactWizardOptions): Promise<void> {
    await page.getByTestId('import-step-next-0').click();
    await expect(page.getByTestId('import-step-next-1')).toBeVisible();

    await page.getByTestId('import-step-next-1').click();
    await expect(page.getByTestId('import-contact-match-key')).toBeVisible();

    if (opts.extraColumnMappings) {
        await applyExtraColumnMappings(page, opts.extraColumnMappings);
    }

    const strategy = opts.duplicateStrategy ?? 'skip';
    await page.getByTestId('import-duplicate-strategy')
        .locator(`input[type="radio"][value="${strategy}"]`)
        .check();

    await page.getByTestId('import-step-next-2').click();
    await expect(page.getByTestId('import-commit-button')).toBeVisible();
}

async function driveNonContactHappyPath(page: Page, key: ImporterKey, opts: NonContactWizardOptions): Promise<void> {
    await openNonContactWizard(page, key);
    await fillUploadStep(page, opts);

    if (opts.contactMissingStrategy && key !== 'events' && key !== 'notes') {
        await applyContactMissingStrategy(page, opts.contactMissingStrategy);
    }

    await advanceNonContactMapping(page, opts);
    await stageAndCommit(page);
}

export async function driveEventsHappyPath(page: Page, opts: NonContactWizardOptions): Promise<void> {
    await driveNonContactHappyPath(page, 'events', opts);
}

export async function driveDonationsHappyPath(page: Page, opts: NonContactWizardOptions): Promise<void> {
    await driveNonContactHappyPath(page, 'donations', opts);
}

export async function driveMembershipsHappyPath(page: Page, opts: NonContactWizardOptions): Promise<void> {
    await driveNonContactHappyPath(page, 'memberships', opts);
}

export async function driveInvoiceDetailsHappyPath(page: Page, opts: NonContactWizardOptions): Promise<void> {
    await driveNonContactHappyPath(page, 'invoice-details', opts);
}

export async function driveNotesHappyPath(page: Page, opts: NonContactWizardOptions): Promise<void> {
    await driveNonContactHappyPath(page, 'notes', opts);
}

export async function approveSessionViaImporter(page: Page, sessionId: string): Promise<void> {
    await page.goto('/admin/importer-page');
    const approve = page.getByTestId(`importer-action-approve-${sessionId}`);
    await expect(approve).toBeVisible();
    await approve.click();
    await page.getByTestId('importer-modal-approve-submit').click();
    await expect(page.getByTestId(`importer-action-approve-${sessionId}`)).toHaveCount(0);
}
