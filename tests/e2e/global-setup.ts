import type { FullConfig } from '@playwright/test';
import { resetDatabase } from './helpers/db.js';
import { captureAdminStorageStateStandalone } from './helpers/auth.js';

export default async function globalSetup(_config: FullConfig): Promise<void> {
    console.log('[globalSetup] migrate:fresh --seed');
    resetDatabase();
    console.log('[globalSetup] logging in admin and capturing storageState');
    await captureAdminStorageStateStandalone();
}
