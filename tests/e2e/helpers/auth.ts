import { chromium } from '@playwright/test';
import type { Browser, Page } from '@playwright/test';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as dotenv from 'dotenv';
import { resetDatabase } from './db.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

dotenv.config({ path: path.resolve(__dirname, '../../../.env') });

export const AUTH_FILE = path.resolve(__dirname, '../.auth/admin.json');
const ADMIN_EMAIL = process.env.ADMIN_EMAIL;
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD;
const BASE_URL = process.env.APP_URL ?? 'http://localhost';

export async function loginAsAdmin(page: Page): Promise<void> {
    if (!ADMIN_EMAIL || !ADMIN_PASSWORD) {
        throw new Error('ADMIN_EMAIL and ADMIN_PASSWORD must be set in .env');
    }

    await page.goto('/admin/login');
    await page.locator('input[type=email]').fill(ADMIN_EMAIL);
    await page.locator('input[type=password]').fill(ADMIN_PASSWORD);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/admin/login'), { timeout: 20_000 });

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
