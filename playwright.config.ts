import { defineConfig, devices } from '@playwright/test';
import * as dotenv from 'dotenv';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

dotenv.config({ path: path.resolve(__dirname, '.env') });

// Browser tests are collected from core AND from every installed plugin package
// that ships them — see docs/adr/0008-browser-tests-travel-with-the-plugin.md.
// A plugin owns and stores the browser tests for the behaviour it ships, but a
// package has no running site to point a browser at, so only the assembled
// application can execute them. This is where the two sets are gathered.
//
// Discovery is reported rather than assumed: a distribution that omits a plugin
// silently omits its browser tests, and a green run that quietly covered less
// must not read the same as a green run that covered everything.
const PLUGIN_VENDOR_DIR = path.resolve(__dirname, 'vendor/nonprofitcrm');

function pluginTestRoots(): string[] {
    if (!fs.existsSync(PLUGIN_VENDOR_DIR)) return [];

    return fs
        .readdirSync(PLUGIN_VENDOR_DIR, { withFileTypes: true })
        .filter((entry) => entry.isDirectory() || entry.isSymbolicLink())
        .map((entry) => entry.name)
        .filter((pkg) => fs.existsSync(path.join(PLUGIN_VENDOR_DIR, pkg, 'tests/e2e')))
        .sort();
}

const collectedFrom = pluginTestRoots();
console.log(
    collectedFrom.length
        ? `[e2e] collecting browser tests from core and ${collectedFrom.length} plugin package(s): ${collectedFrom.join(', ')}`
        : '[e2e] collecting browser tests from core only — no installed plugin package ships tests/e2e',
);

const baseURL = process.env.APP_URL ?? 'http://localhost';
const storageState = 'tests/e2e/.auth/admin.json';

// CI runners are slower and more variable than a local box; give the
// in-browser waits real headroom there so a slow first render isn't
// misread as a failure. Local keeps tight timeouts for fast feedback.
const ci = !!process.env.CI;

export default defineConfig({
    // Rooted at the repository so a plugin package's tests/e2e is reachable;
    // the match patterns, not the root, are what bound collection.
    testDir: __dirname,
    testMatch: [
        '**/tests/e2e/**/*.spec.ts',
        '**/vendor/nonprofitcrm/*/tests/e2e/**/*.spec.ts',
    ],
    testIgnore: ['**/node_modules/**'],
    timeout: ci ? 120_000 : 60_000,
    expect: { timeout: ci ? 20_000 : 10_000 },
    fullyParallel: false,
    workers: 1,
    retries: ci ? 1 : 0,
    reporter: [['list'], ['html', { open: 'never' }]],
    globalSetup: './tests/e2e/global-setup.ts',
    use: {
        baseURL,
        storageState,
        actionTimeout: ci ? 45_000 : 15_000,
        navigationTimeout: ci ? 45_000 : 30_000,
        trace: 'retain-on-failure',
        video: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
            grepInvert: /@stress|@on-demand/,
        },
        {
            name: 'stress',
            use: { ...devices['Desktop Chrome'] },
            grep: /@stress/,
            timeout: 600_000,
        },
        {
            name: 'on-demand',
            use: { ...devices['Desktop Chrome'] },
            grep: /@on-demand/,
            timeout: 300_000,
        },
    ],
});
