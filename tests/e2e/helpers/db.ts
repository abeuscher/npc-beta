import pg from 'pg';
import { execFileSync } from 'node:child_process';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as dotenv from 'dotenv';
import { parseCsv } from './fake-csv.js';

const { Client } = pg;

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

dotenv.config({ path: path.resolve(__dirname, '../../../.env') });

const PROJECT_ROOT = path.resolve(__dirname, '../../..');

export type ContactRow = {
    id: string;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    city: string | null;
    state: string | null;
    import_session_id: string | null;
};

async function withClient<T>(fn: (client: InstanceType<typeof Client>) => Promise<T>): Promise<T> {
    const client = new Client({
        host: 'localhost',
        port: 5432,
        database: process.env.DB_DATABASE,
        user: process.env.DB_USERNAME,
        password: process.env.DB_PASSWORD,
    });

    await client.connect();
    try {
        return await fn(client);
    } finally {
        await client.end();
    }
}

export async function countContacts(): Promise<number> {
    return withClient(async (client) => {
        const res = await client.query<{ count: string }>('SELECT COUNT(*)::text AS count FROM contacts');
        return Number(res.rows[0].count);
    });
}

export async function countContactsInSession(sessionId: string): Promise<number> {
    return withClient(async (client) => {
        const res = await client.query<{ count: string }>(
            'SELECT COUNT(*)::text AS count FROM contacts WHERE import_session_id = $1',
            [sessionId],
        );
        return Number(res.rows[0].count);
    });
}

export async function countStagedUpdatesForSession(sessionId: string): Promise<number> {
    return withClient(async (client) => {
        const res = await client.query<{ count: string }>(
            'SELECT COUNT(*)::text AS count FROM import_staged_updates WHERE import_session_id = $1',
            [sessionId],
        );
        return Number(res.rows[0].count);
    });
}

export async function findContactByEmail(email: string): Promise<ContactRow | null> {
    return withClient(async (client) => {
        const res = await client.query<ContactRow>(
            'SELECT id, first_name, last_name, email, phone, city, state, import_session_id FROM contacts WHERE LOWER(email) = LOWER($1) LIMIT 1',
            [email],
        );
        return res.rows[0] ?? null;
    });
}

export async function findLatestImportSessionId(modelType: string = 'contact'): Promise<string | null> {
    return withClient(async (client) => {
        const res = await client.query<{ id: string }>(
            'SELECT id FROM import_sessions WHERE model_type = $1 ORDER BY created_at DESC LIMIT 1',
            [modelType],
        );
        return res.rows[0]?.id ?? null;
    });
}

async function countInSession(table: string, sessionId: string): Promise<number> {
    return withClient(async (client) => {
        const res = await client.query<{ count: string }>(
            `SELECT COUNT(*)::text AS count FROM ${table} WHERE import_session_id = $1`,
            [sessionId],
        );
        return Number(res.rows[0].count);
    });
}

export async function countEventsInSession(sessionId: string): Promise<number> {
    return countInSession('events', sessionId);
}

export async function countEventRegistrationsInSession(sessionId: string): Promise<number> {
    return countInSession('event_registrations', sessionId);
}

export async function countDonationsInSession(sessionId: string): Promise<number> {
    return countInSession('donations', sessionId);
}

export async function countDonationsInSessionByStatus(sessionId: string, status: string): Promise<number> {
    return withClient(async (client) => {
        const res = await client.query<{ count: string }>(
            'SELECT COUNT(*)::text AS count FROM donations WHERE import_session_id = $1 AND status = $2',
            [sessionId, status],
        );
        return Number(res.rows[0].count);
    });
}

export async function countMembershipsInSession(sessionId: string): Promise<number> {
    return countInSession('memberships', sessionId);
}

export async function countTransactionsInSession(sessionId: string): Promise<number> {
    return countInSession('transactions', sessionId);
}

export async function countNotesInSession(sessionId: string): Promise<number> {
    return countInSession('notes', sessionId);
}

export async function countOrganizationsInSession(sessionId: string): Promise<number> {
    return countInSession('organizations', sessionId);
}

export async function findOrganizationByExternalId(sourceId: string, externalId: string): Promise<Record<string, unknown> | null> {
    return withClient(async (client) => {
        const res = await client.query<Record<string, unknown>>(
            'SELECT * FROM organizations WHERE import_source_id = $1 AND external_id = $2 LIMIT 1',
            [sourceId, externalId],
        );
        return res.rows[0] ?? null;
    });
}

export async function findOrganizationByName(name: string): Promise<Record<string, unknown> | null> {
    return withClient(async (client) => {
        const res = await client.query<Record<string, unknown>>(
            'SELECT * FROM organizations WHERE LOWER(TRIM(name)) = LOWER(TRIM($1)) LIMIT 1',
            [name],
        );
        return res.rows[0] ?? null;
    });
}

export async function countTagsByType(type: string): Promise<number> {
    return withClient(async (client) => {
        const res = await client.query<{ count: string }>(
            'SELECT COUNT(*)::text AS count FROM tags WHERE type = $1',
            [type],
        );
        return Number(res.rows[0].count);
    });
}

export async function getTagsForOrganization(orgId: string): Promise<string[]> {
    return withClient(async (client) => {
        const res = await client.query<{ name: string }>(
            `SELECT t.name FROM tags t
             INNER JOIN taggables tg ON tg.tag_id = t.id
             WHERE tg.taggable_type = 'App\\Models\\Organization' AND tg.taggable_id = $1
             ORDER BY t.name`,
            [orgId],
        );
        return res.rows.map((r) => r.name);
    });
}

export async function countNotesByNotableType(notableType: string): Promise<number> {
    return withClient(async (client) => {
        const res = await client.query<{ count: string }>(
            'SELECT COUNT(*)::text AS count FROM notes WHERE notable_type = $1',
            [notableType],
        );
        return Number(res.rows[0].count);
    });
}

export async function getNotesForOrganization(orgId: string): Promise<Array<{ body: string }>> {
    return withClient(async (client) => {
        const res = await client.query<{ body: string }>(
            `SELECT body FROM notes WHERE notable_type = 'App\\Models\\Organization' AND notable_id = $1 ORDER BY created_at`,
            [orgId],
        );
        return res.rows;
    });
}

export async function findNoteByExternalId(sourceId: string, externalId: string): Promise<Record<string, unknown> | null> {
    return withClient(async (client) => {
        const res = await client.query<Record<string, unknown>>(
            'SELECT * FROM notes WHERE import_source_id = $1 AND external_id = $2 LIMIT 1',
            [sourceId, externalId],
        );
        return res.rows[0] ?? null;
    });
}

async function findByExternalId(modelTable: string, sourceId: string, modelType: string, externalId: string): Promise<Record<string, unknown> | null> {
    return withClient(async (client) => {
        const res = await client.query<Record<string, unknown>>(
            `SELECT m.* FROM ${modelTable} m
             INNER JOIN import_id_maps idm ON idm.model_uuid = m.id
             WHERE idm.import_source_id = $1 AND idm.model_type = $2 AND idm.source_id = $3
             LIMIT 1`,
            [sourceId, modelType, externalId],
        );
        return res.rows[0] ?? null;
    });
}

export async function findEventByExternalId(sourceId: string, externalId: string): Promise<Record<string, unknown> | null> {
    return findByExternalId('events', sourceId, 'event', externalId);
}

export async function findDonationByExternalId(sourceId: string, externalId: string): Promise<Record<string, unknown> | null> {
    return withClient(async (client) => {
        const res = await client.query<Record<string, unknown>>(
            'SELECT * FROM donations WHERE import_source_id = $1 AND external_id = $2 LIMIT 1',
            [sourceId, externalId],
        );
        return res.rows[0] ?? null;
    });
}

export async function findMembershipByExternalId(sourceId: string, externalId: string): Promise<Record<string, unknown> | null> {
    return withClient(async (client) => {
        const res = await client.query<Record<string, unknown>>(
            'SELECT * FROM memberships WHERE import_source_id = $1 AND external_id = $2 LIMIT 1',
            [sourceId, externalId],
        );
        return res.rows[0] ?? null;
    });
}

export async function findTransactionByInvoiceNumber(invoiceNumber: string): Promise<Record<string, unknown> | null> {
    return withClient(async (client) => {
        const res = await client.query<Record<string, unknown>>(
            'SELECT * FROM transactions WHERE invoice_number = $1 ORDER BY created_at DESC LIMIT 1',
            [invoiceNumber],
        );
        return res.rows[0] ?? null;
    });
}

export async function findImportSourceIdByName(name: string): Promise<string | null> {
    return withClient(async (client) => {
        const res = await client.query<{ id: string }>(
            'SELECT id FROM import_sources WHERE name = $1 LIMIT 1',
            [name],
        );
        return res.rows[0]?.id ?? null;
    });
}

const CONTACT_CSV_HEADER_TO_COLUMN: Record<string, string> = {
    'Prefix':         'prefix',
    'First Name':     'first_name',
    'Last Name':      'last_name',
    'Email':          'email',
    'Phone':          'phone',
    'Address Line 1': 'address_line_1',
    'Address Line 2': 'address_line_2',
    'City':           'city',
    'State':          'state',
    'Postal Code':    'postal_code',
    'Country':        'country',
};

export async function insertContactsFromCsv(csvPath: string): Promise<void> {
    const { headers, rows } = parseCsv(csvPath);

    const mapped: Array<{ csvIdx: number; col: string }> = [];
    headers.forEach((h, i) => {
        const col = CONTACT_CSV_HEADER_TO_COLUMN[h];
        if (col) mapped.push({ csvIdx: i, col });
    });

    const emailMapping = mapped.find((m) => m.col === 'email');
    if (!emailMapping) {
        throw new Error(`insertContactsFromCsv: CSV at ${csvPath} has no "Email" column`);
    }

    return withClient(async (client) => {
        for (const row of rows) {
            const email = row[emailMapping.csvIdx];
            if (!email) continue;

            const values: Record<string, string> = {};
            for (const m of mapped) {
                const v = row[m.csvIdx];
                if (v !== undefined && v !== '') values[m.col] = v;
            }

            const cols = Object.keys(values);
            const placeholders = cols.map((_, i) => `$${i + 1}`).join(', ');
            const colList = cols.join(', ');

            await client.query(
                `INSERT INTO contacts (id, source, created_at, updated_at, ${colList})
                 VALUES (gen_random_uuid(), 'manual', NOW(), NOW(), ${placeholders})
                 ON CONFLICT DO NOTHING`,
                cols.map((c) => values[c]),
            );
        }
    });
}

export function resetDatabase(): void {
    execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'migrate:fresh', '--seed'],
        { cwd: PROJECT_ROOT, stdio: 'inherit' },
    );
}

export async function findPageIdBySlug(slug: string): Promise<string | null> {
    return withClient(async (client) => {
        const res = await client.query<{ id: string }>(
            'SELECT id FROM pages WHERE slug = $1 LIMIT 1',
            [slug],
        );
        return res.rows[0]?.id ?? null;
    });
}

export async function createLayoutOnPage(pageId: string): Promise<string> {
    return withClient(async (client) => {
        const res = await client.query<{ id: string }>(
            `INSERT INTO page_layouts
                (id, owner_type, owner_id, label, display, columns, layout_config, appearance_config, sort_order, created_at, updated_at)
             VALUES
                (gen_random_uuid(), 'App\\Models\\Page', $1, 'E2E Layout', 'grid', 2,
                 '{"grid_template_columns":"1fr 1fr","gap":"1rem"}',
                 '{}',
                 0, NOW(), NOW())
             RETURNING id`,
            [pageId],
        );
        return res.rows[0].id;
    });
}

export async function getLayoutAppearanceConfig(layoutId: string): Promise<Record<string, unknown>> {
    return withClient(async (client) => {
        const res = await client.query<{ appearance_config: Record<string, unknown> }>(
            'SELECT appearance_config FROM page_layouts WHERE id = $1',
            [layoutId],
        );
        return res.rows[0]?.appearance_config ?? {};
    });
}

export async function deleteLayout(layoutId: string): Promise<void> {
    await withClient(async (client) => {
        await client.query('DELETE FROM page_layouts WHERE id = $1', [layoutId]);
    });
}

export async function findDashboardConfigIdByRoleName(roleName: string): Promise<string | null> {
    return withClient(async (client) => {
        const res = await client.query<{ id: string }>(
            `SELECT dv.id FROM dashboard_views dv
             JOIN roles r ON r.id = dv.role_id
             WHERE r.name = $1 LIMIT 1`,
            [roleName],
        );
        return res.rows[0]?.id ?? null;
    });
}

export async function countDashboardWidgets(configId: string): Promise<number> {
    return withClient(async (client) => {
        const res = await client.query<{ count: string }>(
            `SELECT COUNT(*)::text AS count FROM page_widgets
             WHERE owner_type = 'App\\WidgetPrimitive\\Views\\DashboardView' AND owner_id = $1`,
            [configId],
        );
        return Number(res.rows[0].count);
    });
}

export async function findDashboardWidgetIdByHandle(configId: string, handle: string): Promise<string | null> {
    return withClient(async (client) => {
        const res = await client.query<{ id: string }>(
            `SELECT pw.id FROM page_widgets pw
             JOIN widget_types wt ON wt.id = pw.widget_type_id
             WHERE pw.owner_type = 'App\\WidgetPrimitive\\Views\\DashboardView'
               AND pw.owner_id = $1
               AND wt.handle = $2
             LIMIT 1`,
            [configId, handle],
        );
        return res.rows[0]?.id ?? null;
    });
}

export async function deleteDashboardWidget(widgetId: string): Promise<void> {
    await withClient(async (client) => {
        await client.query('DELETE FROM page_widgets WHERE id = $1', [widgetId]);
    });
}

export function cleanupImportSession(sessionId: string): void {
    execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'importer:cleanup-session', sessionId],
        { cwd: PROJECT_ROOT, stdio: 'inherit' },
    );
}

export async function cleanupAllImportSessionsOfType(modelType: string): Promise<void> {
    const ids = await withClient(async (client) => {
        const res = await client.query<{ id: string }>(
            'SELECT id FROM import_sessions WHERE model_type = $1',
            [modelType],
        );
        return res.rows.map((r) => r.id);
    });

    for (const id of ids) {
        cleanupImportSession(id);
    }
}

export async function deleteContactsByEmails(emails: string[]): Promise<void> {
    if (emails.length === 0) return;
    await withClient(async (client) => {
        const ids = await client.query<{ id: string }>(
            'SELECT id FROM contacts WHERE LOWER(email) = ANY($1::text[])',
            [emails.map((e) => e.toLowerCase())],
        );
        const contactIds = ids.rows.map((r) => r.id);
        if (contactIds.length === 0) return;

        await client.query(
            `DELETE FROM taggables WHERE taggable_type = 'App\\Models\\Contact' AND taggable_id = ANY($1::uuid[])`,
            [contactIds],
        );
        await client.query('DELETE FROM contacts WHERE id = ANY($1::uuid[])', [contactIds]);
    });
}

export async function findWidgetTypeIdByHandle(handle: string): Promise<string | null> {
    return withClient(async (client) => {
        const res = await client.query<{ id: string }>(
            'SELECT id FROM widget_types WHERE handle = $1 LIMIT 1',
            [handle],
        );
        return res.rows[0]?.id ?? null;
    });
}

export async function listWidgetHandles(): Promise<string[]> {
    return withClient(async (client) => {
        const res = await client.query<{ handle: string }>(
            'SELECT handle FROM widget_types ORDER BY handle',
        );
        return res.rows.map((r) => r.handle);
    });
}

export async function createWidgetOnPage(
    pageId: string,
    handle: string,
    appearanceConfig: Record<string, unknown> = {},
    layoutId: string | null = null,
    columnIndex: number | null = null,
): Promise<string> {
    const widgetTypeId = await findWidgetTypeIdByHandle(handle);
    if (!widgetTypeId) {
        throw new Error(`No widget_type with handle "${handle}"`);
    }

    return withClient(async (client) => {
        const res = await client.query<{ id: string }>(
            `INSERT INTO page_widgets
                (id, owner_type, owner_id, widget_type_id, layout_id, column_index,
                 label, config, query_config, appearance_config,
                 sort_order, is_active, created_at, updated_at)
             VALUES
                (gen_random_uuid(), 'App\\Models\\Page', $1, $2, $3, $4,
                 $5, '{}', '{}', $6,
                 0, true, NOW(), NOW())
             RETURNING id`,
            [
                pageId,
                widgetTypeId,
                layoutId,
                columnIndex,
                `e2e-${handle}`,
                JSON.stringify(appearanceConfig),
            ],
        );
        return res.rows[0].id;
    });
}

export async function deleteWidget(widgetId: string): Promise<void> {
    await withClient(async (client) => {
        await client.query('DELETE FROM page_widgets WHERE id = $1', [widgetId]);
    });
}

export async function updateLayoutConfig(
    layoutId: string,
    layoutConfig: Record<string, unknown>,
): Promise<void> {
    await withClient(async (client) => {
        await client.query(
            'UPDATE page_layouts SET layout_config = $1, updated_at = NOW() WHERE id = $2',
            [JSON.stringify(layoutConfig), layoutId],
        );
    });
}

export async function updateLayoutAppearanceConfig(
    layoutId: string,
    appearanceConfig: Record<string, unknown>,
): Promise<void> {
    await withClient(async (client) => {
        await client.query(
            'UPDATE page_layouts SET appearance_config = $1, updated_at = NOW() WHERE id = $2',
            [JSON.stringify(appearanceConfig), layoutId],
        );
    });
}

export async function getPageSlug(pageId: string): Promise<string | null> {
    return withClient(async (client) => {
        const res = await client.query<{ slug: string }>(
            'SELECT slug FROM pages WHERE id = $1 LIMIT 1',
            [pageId],
        );
        return res.rows[0]?.slug ?? null;
    });
}

export async function findCollectionIdByHandle(handle: string): Promise<string | null> {
    return withClient(async (client) => {
        const res = await client.query<{ id: string }>(
            'SELECT id FROM collections WHERE handle = $1 LIMIT 1',
            [handle],
        );
        return res.rows[0]?.id ?? null;
    });
}

export async function findCollectionItemByTitle(collectionId: string, title: string): Promise<{ id: string; data: Record<string, unknown> } | null> {
    return withClient(async (client) => {
        const res = await client.query<{ id: string; data: Record<string, unknown> }>(
            `SELECT id, data FROM collection_items
             WHERE collection_id = $1 AND data->>'title' = $2
             LIMIT 1`,
            [collectionId, title],
        );
        return res.rows[0] ?? null;
    });
}

export async function deleteCollectionItem(itemId: string): Promise<void> {
    await withClient(async (client) => {
        await client.query('DELETE FROM collection_items WHERE id = $1', [itemId]);
    });
}

export async function findUserIdByEmail(email: string): Promise<string | null> {
    return withClient(async (client) => {
        const res = await client.query<{ id: string }>(
            'SELECT id::text AS id FROM users WHERE LOWER(email) = LOWER($1) LIMIT 1',
            [email],
        );
        return res.rows[0]?.id ?? null;
    });
}

export async function createUserWithRole(
    email: string,
    plainPassword: string,
    roleName: string,
    name: string = 'Test Operator',
): Promise<string> {
    const hash = execFileSync(
        'docker',
        [
            'compose',
            'exec',
            '-T',
            'app',
            'php',
            '-r',
            `echo password_hash('${plainPassword}', PASSWORD_BCRYPT);`,
        ],
        { cwd: PROJECT_ROOT },
    )
        .toString()
        .trim();

    return withClient(async (client) => {
        const insert = await client.query<{ id: string }>(
            `INSERT INTO users (name, email, password, is_active, email_verified_at, created_at, updated_at)
             VALUES ($1, $2, $3, true, NOW(), NOW(), NOW())
             RETURNING id::text AS id`,
            [name, email, hash],
        );
        const userId = insert.rows[0].id;

        const role = await client.query<{ id: string }>(
            'SELECT id::text AS id FROM roles WHERE name = $1 AND guard_name = $2 LIMIT 1',
            [roleName, 'web'],
        );
        if (role.rows.length === 0) {
            throw new Error(`Role '${roleName}' not found.`);
        }

        await client.query(
            `INSERT INTO model_has_roles (role_id, model_type, model_id)
             VALUES ($1, 'App\\Models\\User', $2)`,
            [role.rows[0].id, userId],
        );

        return userId;
    });
}

export async function createContactWithDisplayName(firstName: string, lastName: string): Promise<string> {
    return withClient(async (client) => {
        const res = await client.query<{ id: string }>(
            `INSERT INTO contacts (id, source, first_name, last_name, created_at, updated_at)
             VALUES (gen_random_uuid(), 'manual', $1, $2, NOW(), NOW())
             RETURNING id::text AS id`,
            [firstName, lastName],
        );
        return res.rows[0].id;
    });
}

export async function createNoteForContact(
    contactId: string,
    authorId: string,
    body: string,
    subject: string | null = null,
): Promise<string> {
    return withClient(async (client) => {
        const res = await client.query<{ id: string }>(
            `INSERT INTO notes (id, notable_type, notable_id, author_id, type, status, subject, body, occurred_at, created_at, updated_at)
             VALUES (gen_random_uuid(), 'App\\Models\\Contact', $1, $2, 'note', 'completed', $3, $4, NOW(), NOW(), NOW())
             RETURNING id::text AS id`,
            [contactId, authorId, subject, body],
        );
        return res.rows[0].id;
    });
}

export async function grantPermissionToUser(userId: string, permissionName: string): Promise<void> {
    await withClient(async (client) => {
        const perm = await client.query<{ id: string }>(
            'SELECT id::text AS id FROM permissions WHERE name = $1 AND guard_name = $2 LIMIT 1',
            [permissionName, 'web'],
        );
        if (perm.rows.length === 0) {
            throw new Error(`Permission '${permissionName}' not found.`);
        }

        await client.query(
            `INSERT INTO model_has_permissions (permission_id, model_type, model_id)
             VALUES ($1, 'App\\Models\\User', $2)
             ON CONFLICT DO NOTHING`,
            [perm.rows[0].id, userId],
        );
    });
}

export function clearSiteSettingCache(): void {
    execFileSync(
        'docker',
        ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'cache:clear'],
        { cwd: PROJECT_ROOT, stdio: 'inherit' },
    );
}
