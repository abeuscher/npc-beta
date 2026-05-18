import { defineConfig, devices } from '@playwright/test';
import * as dotenv from 'dotenv';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

dotenv.config({ path: path.resolve(__dirname, '.env') });

const baseURL = process.env.APP_URL ?? 'http://localhost';
const storageState = 'tests/e2e/.auth/admin.json';

// CI runners are slower and more variable than a local box; give the
// in-browser waits real headroom there so a slow first render isn't
// misread as a failure. Local keeps tight timeouts for fast feedback.
const ci = !!process.env.CI;

export default defineConfig({
    testDir: './tests/e2e',
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
