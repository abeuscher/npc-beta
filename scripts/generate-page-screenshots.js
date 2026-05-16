#!/usr/bin/env node
/**
 * Capture full-page PNG screenshots of the five published public marketing
 * pages at their live URLs, for the Public Marketing Website track record.
 *
 * Usage (run on the host, NOT inside Docker):
 *   node scripts/generate-page-screenshots.js
 *   node scripts/generate-page-screenshots.js --base-url=http://localhost:8080
 *
 * Requirements (install once, globally):
 *   npm install --global playwright
 *   npx playwright install chromium
 *
 * If the global install is not visible to `require`, run with:
 *   NODE_PATH="$(npm root -g)" node scripts/generate-page-screenshots.js ...
 *
 * Run on demand, not in CI. Each PNG is written to
 * `sessions/public website/screenshots/page-{slug}.png` and committed so the
 * pages can be reviewed without running the script.
 */

import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const VIEWPORT = { width: 1280, height: 900 };
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const PROJECT_ROOT = path.resolve(__dirname, '..');
const OUT_DIR = path.join(PROJECT_ROOT, 'sessions', 'public website', 'screenshots');

const PAGES = [
    { slug: 'home', urlPath: '/' },
    { slug: 'about', urlPath: '/about' },
    { slug: 'pricing', urlPath: '/pricing' },
    { slug: 'contact', urlPath: '/contact' },
    { slug: 'demo', urlPath: '/demo' },
];

function parseArgs(argv) {
    const args = {
        baseUrl: 'http://localhost',
    };
    for (const raw of argv.slice(2)) {
        if (raw.startsWith('--base-url=')) {
            args.baseUrl = raw.slice('--base-url='.length).replace(/\/$/, '');
        }
    }
    return args;
}

async function main() {
    const args = parseArgs(process.argv);

    let playwright;
    try {
        playwright = await import('playwright');
    } catch (err) {
        try {
            const globalRoot = execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim();
            const candidate = path.join(globalRoot, 'playwright', 'index.js');
            if (fs.existsSync(candidate)) {
                playwright = await import(candidate);
            } else {
                throw err;
            }
        } catch (inner) {
            console.error('Playwright is not installed. Install globally with:');
            console.error('  npm install --global playwright');
            console.error('  npx playwright install chromium');
            process.exit(1);
        }
    }

    const chromium = playwright.chromium ?? playwright.default?.chromium;
    if (!chromium) {
        console.error('Playwright module does not expose chromium — unexpected module shape.');
        process.exit(1);
    }

    fs.mkdirSync(OUT_DIR, { recursive: true });

    const browser = await chromium.launch();
    const context = await browser.newContext({ viewport: VIEWPORT });

    let failures = 0;

    for (const { slug, urlPath } of PAGES) {
        const url = `${args.baseUrl}${urlPath}`;
        const outFile = path.join(OUT_DIR, `page-${slug}.png`);
        const page = await context.newPage();
        try {
            const response = await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
            if (!response || !response.ok()) {
                failures += 1;
                console.error(`FAIL ${slug}: HTTP ${response ? response.status() : 'no response'} at ${url}`);
                await page.close();
                continue;
            }
            await page.evaluate(() => (document.fonts ? document.fonts.ready : Promise.resolve()));
            await page.evaluate(() => {
                document.querySelectorAll('.phpdebugbar, #phpdebugbar').forEach((el) => el.remove());
            });
            await page.screenshot({ path: outFile, type: 'png', fullPage: true });
            console.log(`OK   ${slug} → ${path.relative(PROJECT_ROOT, outFile)}`);
        } catch (err) {
            failures += 1;
            console.error(`FAIL ${slug}: ${err.message}`);
        } finally {
            await page.close();
        }
    }

    await browser.close();

    if (failures > 0) {
        process.exit(1);
    }
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
