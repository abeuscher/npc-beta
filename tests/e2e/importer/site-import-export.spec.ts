import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import * as os from 'node:os';
import { fileURLToPath } from 'node:url';
import { resetAndLogin } from '../helpers/auth.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

test.describe.configure({ mode: 'serial' });

test.describe('Site import / export rollup UI (session 310)', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test('renders both sections and surfaces snapshot counts on Export Site', async ({ page }) => {
        test.setTimeout(60_000);

        // DIAGNOSTIC (temporary, A003): collect browser console + network failures.
        const consoleMessages: string[] = [];
        page.on('console', (msg) => consoleMessages.push(`[${msg.type()}] ${msg.text()}`));
        page.on('pageerror', (err) => consoleMessages.push(`[pageerror] ${err.message}`));
        page.on('requestfailed', (req) => consoleMessages.push(`[reqfailed] ${req.method()} ${req.url()} - ${req.failure()?.errorText}`));
        page.on('response', (res) => {
            if (res.status() >= 400) consoleMessages.push(`[resp ${res.status()}] ${res.request().method()} ${res.url()}`);
        });

        await page.goto('/admin/site-import-export-page');

        // Both narrative sections render at page load.
        await expect(page.locator('[data-testid="site-export-section"]')).toBeVisible();
        await expect(page.locator('[data-testid="site-import-section"]')).toBeVisible();

        // DIAGNOSTIC: dump the header-actions area of the rendered page.
        const headerActionsHtml = await page.locator('header, .fi-page-header, .fi-header').first().innerHTML().catch(() => '(header not found)');
        console.log('=== A003-DIAG: header HTML ===');
        console.log(headerActionsHtml.slice(0, 4000));
        console.log('=== A003-DIAG: site-export-action selector match count ===');
        console.log(String(await page.locator('[data-testid="site-export-action"]').count()));
        console.log('=== A003-DIAG: button[name=Export Site] count ===');
        console.log(String(await page.getByRole('button', { name: 'Export Site' }).count()));
        const actionEl = page.locator('[data-testid="site-export-action"]').first();
        if (await actionEl.count() > 0) {
            const tag = await actionEl.evaluate((el) => el.tagName).catch(() => '?');
            const outerHtml = await actionEl.evaluate((el) => el.outerHTML.slice(0, 800)).catch(() => '?');
            console.log(`=== A003-DIAG: testid-element tag=${tag} ===`);
            console.log(outerHtml);
        }

        // Export Site button opens a confirmation modal with snapshot counts.
        // Click via the action's data-testid (set in SiteImportExportPage::
        // exportSiteAction) — `getByRole('button', { name: 'Export Site' })`
        // is ambiguous because `modalSubmitActionLabel` also labels the
        // submit button "Export Site". Filament also pre-renders modal shells
        // in the DOM with `isOpen: false`, so the modal selector filters to
        // visible dialogs only.
        await page.locator('[data-testid="site-export-action"]').click();

        // DIAGNOSTIC: brief wait then dump dialog state.
        await page.waitForTimeout(2000);
        console.log('=== A003-DIAG: dialog count after click ===');
        console.log(String(await page.locator('[role="dialog"]').count()));
        console.log('=== A003-DIAG: visible dialog count ===');
        console.log(String(await page.locator('[role="dialog"]:visible').count()));
        const allDialogs = await page.locator('[role="dialog"]').all();
        for (let i = 0; i < allDialogs.length; i++) {
            const visible = await allDialogs[i].isVisible().catch(() => false);
            const ariaLabel = await allDialogs[i].getAttribute('aria-labelledby').catch(() => '?');
            const text = await allDialogs[i].innerText().catch(() => '?');
            console.log(`=== A003-DIAG: dialog[${i}] visible=${visible} aria-labelledby=${ariaLabel} text=${text.slice(0, 200)} ===`);
        }
        console.log('=== A003-DIAG: console+network messages ===');
        for (const m of consoleMessages.slice(-30)) console.log(m);

        const modal = page.locator('[role="dialog"]:visible').filter({ hasText: 'Export Site' });
        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal).toContainText(/page/);
        await expect(modal).toContainText(/template/);
        await expect(modal).toContainText(/theme/);
        await expect(modal).toContainText(/media/);

        // Close the export modal without dispatching.
        await modal.getByRole('button', { name: 'Cancel' }).click();
        await expect(modal).toBeHidden({ timeout: 10_000 });
    });

    test('Import Site analyzes the bundle on upload and reveals gated toggles', async ({ page }) => {
        test.setTimeout(120_000);

        const bundle = {
            format_version: '1.1.0',
            exported_at: new Date().toISOString(),
            payload: {
                design: {
                    theme_colors: { palette: { primary: '#abcdef' } },
                },
                pages: [
                    { title: 'Imported Home', slug: 'home', type: 'default', status: 'draft', widgets: [] },
                    { title: 'Imported Fresh 310', slug: 'imported-fresh-310', type: 'default', status: 'draft', widgets: [] },
                ],
                templates: [],
            },
        };

        const tmpFile = path.join(os.tmpdir(), `session-310-site-bundle-${Date.now()}.json`);
        fs.writeFileSync(tmpFile, JSON.stringify(bundle));

        try {
            await page.goto('/admin/site-import-export-page');

            await page.locator('[data-testid="site-import-action"]').click();

            const upload = page.locator('[data-testid="import-bundle-upload"]');
            await expect(upload).toBeVisible({ timeout: 15_000 });
            await expect(upload.locator('.filepond--root')).toBeVisible({ timeout: 30_000 });

            const fileInput = upload.locator('input[type="file"]').first();
            await fileInput.setInputFiles(tmpFile);

            await expect(upload.locator('p', { hasText: /Uploading file/i })).toBeHidden({ timeout: 60_000 });
            await expect.poll(
                async () => upload.locator('[data-filepond-item-state="processing-complete"]').count(),
                { timeout: 60_000, intervals: [200, 500, 1_000] },
            ).toBeGreaterThan(0);
            await page.waitForLoadState('networkidle');

            const summary = page.locator('[data-testid="import-bundle-manifest-summary"]');
            await expect(summary).toBeVisible({ timeout: 30_000 });
            await expect(summary).toContainText('theme payload');
            await expect(summary).toContainText('2 pages');

            // The four gated toggles match session 309's shape — reuse is the point.
            await expect(page.getByRole('switch', { name: /Replace site theme/i })).toBeVisible();
            await expect(page.getByRole('switch', { name: /Import 2 pages/i })).toBeVisible();
            await expect(page.getByRole('switch', { name: /Replace duplicate pages/i })).toBeVisible();

            // Single "Import" submit button (no wizard "Next").
            await expect(page.getByRole('button', { name: 'Next' })).toHaveCount(0);
            await expect(page.getByRole('button', { name: 'Import', exact: true })).toBeVisible();
        } finally {
            if (fs.existsSync(tmpFile)) {
                fs.unlinkSync(tmpFile);
            }
        }
    });
});
