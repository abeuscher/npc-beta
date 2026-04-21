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

export async function countMembershipsInSession(sessionId: string): Promise<number> {
    return countInSession('memberships', sessionId);
}

export async function countTransactionsInSession(sessionId: string): Promise<number> {
    return countInSession('transactions', sessionId);
}

export async function countNotesInSession(sessionId: string): Promise<number> {
    return countInSession('notes', sessionId);
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
