import { test, expect } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import {
    findCollectionIdByHandle,
    findCollectionItemByTitle,
    deleteCollectionItem,
} from '../helpers/db.js';

test.describe.configure({ mode: 'serial' });

test.describe('Memos — Trix → Quill convergence', () => {
    let memosId: string | null = null;
    let createdItemId: string | null = null;

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        memosId = await findCollectionIdByHandle('memos');
        expect(memosId).not.toBeNull();
    });

    test.afterAll(async () => {
        if (createdItemId !== null) {
            await deleteCollectionItem(createdItemId);
            createdItemId = null;
        }
    });

    test('Memos item create modal mounts QuillEditor (not Trix)', async ({ page }) => {
        await page.goto(`/admin/browse-collections/${memosId}/items`);

        const createBtn = page.getByRole('button', { name: /create|new/i }).first();
        await expect(createBtn).toBeVisible({ timeout: 15_000 });
        await createBtn.click();

        await expect(page.locator('.ql-toolbar').first()).toBeVisible({ timeout: 15_000 });
        await expect(page.locator('.ql-editor').first()).toBeVisible();

        await expect(page.locator('trix-editor')).toHaveCount(0);
    });

    test('Memos item save round-trip persists Quill-authored content', async ({ page }) => {
        await page.goto(`/admin/browse-collections/${memosId}/items`);

        const createBtn = page.getByRole('button', { name: /create|new/i }).first();
        await createBtn.click();

        await expect(page.locator('.ql-editor').first()).toBeVisible({ timeout: 15_000 });

        await page.getByLabel('Title').first().fill('E2E Memo');

        const editor = page.locator('.ql-editor').first();
        await editor.click();
        await page.keyboard.type('Hello from Playwright');

        await page.getByRole('button', { name: /^create$/i }).first().click();

        await expect(page.getByRole('cell', { name: 'E2E Memo' })).toBeVisible({ timeout: 15_000 });

        const item = await findCollectionItemByTitle(memosId!, 'E2E Memo');
        expect(item).not.toBeNull();
        createdItemId = item!.id;

        const data = item!.data as { title?: string; body?: string };
        expect(data.title).toBe('E2E Memo');
        expect(data.body).toContain('Hello from Playwright');
    });
});
