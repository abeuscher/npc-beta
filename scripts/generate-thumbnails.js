#!/usr/bin/env node
/**
 * Capture static PNG thumbnails of widgets rendered via the dev demo route.
 *
 * Usage (run on the host, NOT inside Docker):
 *   node scripts/generate-thumbnails.js            # every registered widget
 *   node scripts/generate-thumbnails.js --all
 *   node scripts/generate-thumbnails.js --widget=text_block
 *   node scripts/generate-thumbnails.js --widget=hero --preset=bold-statement
 *   node scripts/generate-thumbnails.js --base-url=http://localhost:8080
 *
 * Requirements (install once, globally):
 *   npm install --global playwright
 *   npx playwright install chromium
 *
 * If the global install is not visible to `require`, run with:
 *   NODE_PATH="$(npm root -g)" node scripts/generate-thumbnails.js ...
 *
 * Widget list is sourced from `docker compose exec app php artisan widgets:manifest-json`.
 * Each PNG is written to `app/Widgets/{PascalFolder}/thumbnails/static.png`.
 */

import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const VIEWPORT = { width: 800, height: 500 };
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const PROJECT_ROOT = path.resolve(__dirname, '..');

function parseArgs(argv) {
    const args = {
        all: false,
        widget: null,
        preset: null,
        baseUrl: 'http://localhost',
        doStatic: true,
        doPresets: true,
    };
    for (const raw of argv.slice(2)) {
        if (raw === '--all') {
            args.all = true;
        } else if (raw === '--no-static') {
            args.doStatic = false;
        } else if (raw === '--no-presets') {
            args.doPresets = false;
        } else if (raw.startsWith('--widget=')) {
            args.widget = raw.slice('--widget='.length);
        } else if (raw.startsWith('--preset=')) {
            args.preset = raw.slice('--preset='.length);
        } else if (raw.startsWith('--base-url=')) {
            args.baseUrl = raw.slice('--base-url='.length).replace(/\/$/, '');
        }
    }
    if (!args.widget) {
        args.all = true;
    }
    if (args.preset) {
        args.doStatic = false;
    }
    return args;
}

function pascalFolder(handle) {
    return handle
        .split('_')
        .map((s) => s.charAt(0).toUpperCase() + s.slice(1))
        .join('');
}

function loadManifest() {
    const output = execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'widgets:manifest-json'],
        { cwd: PROJECT_ROOT, encoding: 'utf8' },
    );
    return JSON.parse(output);
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

    const manifest = loadManifest();
    const handles = args.widget ? [args.widget] : Object.keys(manifest);

    if (args.widget && !manifest[args.widget]) {
        console.error(`Unknown widget handle: ${args.widget}`);
        process.exit(1);
    }

    const chromium = playwright.chromium ?? playwright.default?.chromium;
    if (!chromium) {
        console.error('Playwright module does not expose chromium — unexpected module shape.');
        process.exit(1);
    }

    const browser = await chromium.launch();
    const context = await browser.newContext({ viewport: VIEWPORT });

    let failures = 0;

    for (const handle of handles) {
        const outDir = path.join(PROJECT_ROOT, 'app', 'Widgets', pascalFolder(handle), 'thumbnails');

        if (args.doStatic) {
            const url = `${args.baseUrl}/dev/widgets/${handle}`;
            const outFile = path.join(outDir, 'static.png');
            const page = await context.newPage();
            try {
                const response = await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
                if (!response || !response.ok()) {
                    failures += 1;
                    console.error(`FAIL ${handle}: HTTP ${response ? response.status() : 'no response'} at ${url}`);
                    await page.close();
                    continue;
                }
                fs.mkdirSync(outDir, { recursive: true });
                await page.screenshot({ path: outFile, type: 'png' });
                console.log(`OK   ${handle} → ${path.relative(PROJECT_ROOT, outFile)}`);
            } catch (err) {
                failures += 1;
                console.error(`FAIL ${handle}: ${err.message}`);
            } finally {
                await page.close();
            }
        }

        const presets = args.doPresets ? (manifest[handle]?.presets ?? []) : [];
        for (const preset of presets) {
            if (args.preset && preset.handle !== args.preset) {
                continue;
            }
            const presetUrl = `${args.baseUrl}/dev/widgets/${handle}/presets/${preset.handle}`;
            const presetFile = path.join(outDir, `preset-${preset.handle}.png`);
            const presetPage = await context.newPage();
            try {
                const response = await presetPage.goto(presetUrl, { waitUntil: 'networkidle', timeout: 30000 });
                if (!response || !response.ok()) {
                    failures += 1;
                    console.error(`FAIL ${handle}/${preset.handle}: HTTP ${response ? response.status() : 'no response'} at ${presetUrl}`);
                    await presetPage.close();
                    continue;
                }
                fs.mkdirSync(outDir, { recursive: true });
                await presetPage.screenshot({ path: presetFile, type: 'png' });
                console.log(`OK   ${handle}/${preset.handle} → ${path.relative(PROJECT_ROOT, presetFile)}`);
            } catch (err) {
                failures += 1;
                console.error(`FAIL ${handle}/${preset.handle}: ${err.message}`);
            } finally {
                await presetPage.close();
            }
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
