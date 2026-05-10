import { test, expect, type Page } from '@playwright/test';
import { resetAndLogin, loginAs } from '../helpers/auth.js';
import {
    createUserWithRole,
    createContactWithDisplayName,
    createNoteForContact,
    grantPermissionToUser,
    clearSiteSettingCache,
} from '../helpers/db.js';

test.describe.configure({ mode: 'serial' });

test.describe('Notes Permissions — UI policy gate', () => {
    const OP_A_EMAIL = 'op_a@notes-perm.test';
    const OP_B_EMAIL = 'op_b@notes-perm.test';
    const OP_PASSWORD = 'password-test-276';
    const NOTE_BODY = 'Original note body authored by op_a';
    const NOTE_SUBJECT = '276 policy gate test';

    let opA_userId: string;
    let opB_userId: string;
    let contactId: string;
    let noteId: string;

    async function gotoExpandedTimeline(page: Page): Promise<void> {
        await page.goto(`/admin/contacts/${contactId}/notes`);
        await expect(page.getByText(NOTE_SUBJECT)).toBeVisible({ timeout: 10_000 });
        await page.getByRole('button', { name: 'Expand all' }).click();
        await expect(page.getByText(NOTE_BODY)).toBeVisible({ timeout: 5_000 });
    }

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        clearSiteSettingCache();

        opA_userId = await createUserWithRole(OP_A_EMAIL, OP_PASSWORD, 'crm_editor', 'Op A');
        opB_userId = await createUserWithRole(OP_B_EMAIL, OP_PASSWORD, 'crm_editor', 'Op B');
        contactId = await createContactWithDisplayName('Edit', 'Test');
        noteId = await createNoteForContact(contactId, opA_userId, NOTE_BODY, NOTE_SUBJECT);
    });

    test('toggle off baseline — op_b sees Edit and Delete on op_a-authored note', async ({ page }) => {
        await loginAs(page, OP_B_EMAIL, OP_PASSWORD);
        await gotoExpandedTimeline(page);

        await expect(page.locator('button.np-timeline-menu__btn', { hasText: 'Edit' })).toBeVisible();
        await expect(page.locator('button.np-timeline-menu__btn', { hasText: 'Delete' })).toBeVisible();
    });

    test('toggle on — op_b cannot see Edit / Delete on op_a-authored note', async ({ page }) => {
        await loginAs(page, process.env.ADMIN_EMAIL!, process.env.ADMIN_PASSWORD!);
        await page.goto('/admin/general-settings-page');
        await page.getByLabel('Restrict note edits to author').check();
        await page.getByRole('button', { name: 'Save Notes' }).click();
        await expect(page.getByRole('heading', { name: 'Notes saved' })).toBeVisible({ timeout: 10_000 });

        clearSiteSettingCache();

        await loginAs(page, OP_B_EMAIL, OP_PASSWORD);
        await gotoExpandedTimeline(page);

        await expect(page.locator('button.np-timeline-menu__btn', { hasText: 'Edit' })).toHaveCount(0);
        await expect(page.locator('button.np-timeline-menu__btn', { hasText: 'Delete' })).toHaveCount(0);
    });

    test('toggle on + edit_others_note granted — op_b can edit again', async ({ page }) => {
        await grantPermissionToUser(opB_userId, 'edit_others_note');
        clearSiteSettingCache();

        await loginAs(page, OP_B_EMAIL, OP_PASSWORD);
        await gotoExpandedTimeline(page);

        await expect(page.locator('button.np-timeline-menu__btn', { hasText: 'Edit' })).toBeVisible();
        await expect(page.locator('button.np-timeline-menu__btn', { hasText: 'Delete' })).toBeVisible();
    });
});
