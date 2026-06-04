// On-demand capture of the Import showcase screenshot (session 338).
//
// The demo role is walled off from the real Importer (DemoRoleLockdownTest), so
// the tour shows a captured screenshot of it instead — the same committed-PNG
// model the repo already uses for widget thumbnails. This is NOT a regression
// test: it is tagged @on-demand (excluded from the default chromium run) and is
// re-run by hand to refresh the image when the importer UI changes:
//
//   npm run test:e2e:on-demand -- tests/e2e/tour/capture-import-showcase.spec.ts
//
// It writes public/images/tour/import-showcase.png, which the
// TourImportShowcasePage renders.

import { test } from '@playwright/test';
import * as fs from 'node:fs';
import * as path from 'node:path';

test('@on-demand capture the importer screen for the tour showcase', async ({ page }) => {
    const outDir = path.resolve(process.cwd(), 'public/images/tour');
    fs.mkdirSync(outDir, { recursive: true });

    await page.setViewportSize({ width: 1440, height: 1024 });
    await page.goto('/admin/importer-page');
    await page.locator('main').first().waitFor({ state: 'visible' });
    // Let the page settle (icons, table) before the shot.
    await page.waitForTimeout(1_000);

    await page.locator('main').first().screenshot({
        path: path.join(outDir, 'import-showcase.png'),
    });
});
