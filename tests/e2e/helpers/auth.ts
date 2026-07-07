import { chromium } from '@playwright/test';
import type { Browser, Page } from '@playwright/test';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as dotenv from 'dotenv';
import { resetDatabase, enrollTwoFactor, E2E_ADMIN_RECOVERY_CODE } from './db.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

dotenv.config({ path: path.resolve(__dirname, '../../../.env') });

export const AUTH_FILE = path.resolve(__dirname, '../.auth/admin.json');
const ADMIN_EMAIL = process.env.ADMIN_EMAIL;
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD;
const BASE_URL = process.env.APP_URL ?? 'http://localhost';

// CI runners are slower and more variable than a local box; the challenge's
// Livewire round-trip has been observed to blow a flat 20s there.
const LOGIN_WAIT = process.env.CI ? 45_000 : 20_000;

// 2FA enforcement gate (session 359): an enrolled user lands on the challenge
// after the password step. Enrolled users carry the known recovery code
// (db.ts), so clear it with that — no TOTP time-window.
async function clearTwoFactorChallenge(page: Page): Promise<void> {
    if (page.url().includes('two-factor-challenge')) {
        await page.getByLabel('Authentication code').fill(E2E_ADMIN_RECOVERY_CODE);
        await page.getByRole('button', { name: 'Verify' }).click();
        await page.waitForURL((url) => !url.pathname.includes('two-factor'), { timeout: LOGIN_WAIT });
    }
}

export async function loginAsAdmin(page: Page): Promise<void> {
    if (!ADMIN_EMAIL || !ADMIN_PASSWORD) {
        throw new Error('ADMIN_EMAIL and ADMIN_PASSWORD must be set in .env');
    }

    await page.goto('/admin/login');
    await page.locator('input[type=email]').fill(ADMIN_EMAIL);
    await page.locator('input[type=password]').fill(ADMIN_PASSWORD);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/admin/login'), { timeout: LOGIN_WAIT });

    await clearTwoFactorChallenge(page);

    await page.goto('/admin/import-contacts-page');
    if (page.url().includes('/admin/login')) {
        throw new Error('Login failed — redirected back to /admin/login');
    }
}

export async function captureFreshStorageState(browser: Browser): Promise<void> {
    const context = await browser.newContext({ baseURL: BASE_URL });
    const page = await context.newPage();
    try {
        await loginAsAdmin(page);
        await context.storageState({ path: AUTH_FILE });
    } finally {
        await context.close();
    }
}

export async function resetAndLogin(browser: Browser): Promise<void> {
    resetDatabase();
    await captureFreshStorageState(browser);
}

export async function captureAdminStorageStateStandalone(): Promise<void> {
    const browser = await chromium.launch();
    try {
        await captureFreshStorageState(browser);
    } finally {
        await browser.close();
    }
}

export async function loginAs(page: Page, email: string, password: string): Promise<void> {
    // The 2FA enforcement gate (session 359) covers every admin-panel user,
    // not just the seeded admin — an un-enrolled user is redirected to the
    // enrollment page and never reaches the screen under test. Enroll (or
    // re-enroll) this user with the fixed recovery code before logging in;
    // re-enrolling also restores the code a previous login consumed
    // (recovery codes are single-use).
    enrollTwoFactor(email);

    await page.context().clearCookies();
    await page.goto('/admin/login');
    await page.locator('input[type=email]').fill(email);
    await page.locator('input[type=password]').fill(password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/admin/login'), { timeout: LOGIN_WAIT });

    await clearTwoFactorChallenge(page);
}
