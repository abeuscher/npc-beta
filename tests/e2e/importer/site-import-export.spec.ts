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

        await page.goto('/admin/site-import-export');

        // Both narrative sections render at page load.
        await expect(page.locator('[data-testid="site-export-section"]')).toBeVisible();
        await expect(page.locator('[data-testid="site-import-section"]')).toBeVisible();

        // Export Site button opens a confirmation modal with snapshot counts.
        await page.getByRole('button', { name: 'Export Site', exact: true }).click();

        const modal = page.locator('.fi-modal-window, [role="dialog"]').filter({ hasText: 'Export Site' }).first();
        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal).toContainText(/page/);
        await expect(modal).toContainText(/template/);
        await expect(modal).toContainText(/theme/);
        await expect(modal).toContainText(/media/);

        // Close the export modal without dispatching.
        await page.getByRole('button', { name: 'Cancel' }).first().click();
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
            await page.goto('/admin/site-import-export');

            await page.getByRole('button', { name: 'Import Site', exact: true }).click();

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
