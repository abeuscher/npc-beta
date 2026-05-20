import { test, expect } from '@playwright/test';
import * as path from 'node:path';
import * as fs from 'node:fs';
import * as os from 'node:os';
import { fileURLToPath } from 'node:url';
import { resetAndLogin } from '../helpers/auth.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

test.describe.configure({ mode: 'serial' });

test.describe('Content bundle importer — live-reveal opt-in UI (session 309)', () => {
    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test('analyzes the bundle on upload and reveals gated theme + page toggles', async ({ page }) => {
        test.setTimeout(120_000);

        // Build a tiny JSON bundle with a theme payload AND a slug that
        // exists locally after a fresh seed (home is one of the seeded pages).
        const bundle = {
            format_version: '1.1.0',
            exported_at: new Date().toISOString(),
            payload: {
                design: {
                    theme_colors: { palette: { primary: '#abcdef' } },
                    typography:   { buckets: { heading_family: 'Inter' } },
                },
                pages: [
                    { title: 'Imported Home', slug: 'home', type: 'default', status: 'draft', widgets: [] },
                    { title: 'Imported Fresh', slug: 'imported-fresh-309', type: 'default', status: 'draft', widgets: [] },
                ],
                templates: [],
            },
        };

        const tmpFile = path.join(os.tmpdir(), `session-309-bundle-${Date.now()}.json`);
        fs.writeFileSync(tmpFile, JSON.stringify(bundle));

        try {
            await page.goto('/admin/pages');

            await page.getByRole('button', { name: 'Import Bundle' }).click();

            // Modal renders the upload field. FilePond lazy-loads via Alpine;
            // wait until it has mounted before handing it the file — otherwise
            // the upload pipeline never starts.
            const upload = page.locator('[data-testid="import-bundle-upload"]');
            await expect(upload).toBeVisible({ timeout: 15_000 });
            await expect(upload.locator('.filepond--root')).toBeVisible({ timeout: 30_000 });

            const fileInput = upload.locator('input[type="file"]').first();
            await fileInput.setInputFiles(tmpFile);

            // FilePond shows "Uploading file" while the byte transfer is in
            // flight; once it leaves, Filament finalises Livewire state.
            await expect(upload.locator('p', { hasText: /Uploading file/i })).toBeHidden({ timeout: 60_000 });

            // The most durable signal that the upload landed: FilePond writes
            // `processing-complete` to its item state attribute. Poll because
            // Filament's ->live() roundtrip can briefly re-render the node.
            await expect.poll(
                async () => upload.locator('[data-filepond-item-state="processing-complete"]').count(),
                { timeout: 60_000, intervals: [200, 500, 1_000] },
            ).toBeGreaterThan(0);
            await page.waitForLoadState('networkidle');

            // The manifest summary appears in the same modal page — no wizard
            // navigation. Its visibility is the signal that analyze() ran.
            const summary = page.locator('[data-testid="import-bundle-manifest-summary"]');
            await expect(summary).toBeVisible({ timeout: 30_000 });
            await expect(summary).toContainText('theme payload');
            await expect(summary).toContainText('2 pages');

            // "Replace site theme" — visible (design present) and OFF.
            const themeSwitch = page.getByRole('switch', { name: /Replace site theme/i });
            await expect(themeSwitch).toBeVisible();
            await expect(themeSwitch).toHaveAttribute('aria-checked', 'false');

            // "Import N pages" — visible (bundle has pages) and ON by default.
            const pagesSwitch = page.getByRole('switch', { name: /Import 2 pages/i });
            await expect(pagesSwitch).toBeVisible();
            await expect(pagesSwitch).toHaveAttribute('aria-checked', 'true');

            // "Replace duplicate pages" — visible (home is a fresh-seed
            // duplicate) and OFF.
            const dupSwitch = page.getByRole('switch', { name: /Replace duplicate pages/i });
            await expect(dupSwitch).toBeVisible();
            await expect(dupSwitch).toHaveAttribute('aria-checked', 'false');

            // "Include media" — hidden, bundle has no media.
            await expect(page.getByRole('switch', { name: /Include .* media files/i })).toHaveCount(0);

            // Single "Import" submit button exists (no wizard "Next").
            await expect(page.getByRole('button', { name: 'Next' })).toHaveCount(0);
            await expect(page.getByRole('button', { name: 'Import', exact: true })).toBeVisible();
        } finally {
            if (fs.existsSync(tmpFile)) {
                fs.unlinkSync(tmpFile);
            }
        }
    });
});
