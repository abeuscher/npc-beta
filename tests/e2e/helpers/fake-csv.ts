import { execFileSync } from 'node:child_process';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const PROJECT_ROOT = path.resolve(__dirname, '../../..');
const HOST_OUT_DIR = path.resolve(PROJECT_ROOT, 'storage/app/fake-csvs');

export const FAKE_SOURCE_LABELS = {
    contacts:       'Demo Fake Data — Contacts',
    events:         'Demo Fake Data — Events',
    donations:      'Demo Fake Data — Donations',
    memberships:    'Demo Fake Data — Memberships',
    invoiceDetails: 'Demo Fake Data — Invoice Details',
    notes:          'Demo Fake Data — Notes',
} as const;

let generated = false;

export function generateFakeCsvs(seed?: number): string {
    const args = ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'csv:generate-fake-imports'];
    if (seed !== undefined) {
        args.push(`--seed=${seed}`);
    }
    execFileSync('docker', args, { cwd: PROJECT_ROOT, stdio: 'inherit' });
    generated = true;
    return HOST_OUT_DIR;
}

export function seedFakeImportSources(force = true): void {
    const args = ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'seed:fake-import-sources'];
    if (force) {
        args.push('--force');
    }
    execFileSync('docker', args, { cwd: PROJECT_ROOT, stdio: 'inherit' });
}

export function primeFakeFixtures(seed?: number): void {
    if (! generated) {
        generateFakeCsvs(seed);
    }
    seedFakeImportSources(true);
}

export type CsvName = 'contacts.csv' | 'events.csv' | 'donations.csv' | 'memberships.csv' | 'invoice_details.csv' | 'notes.csv';

export function cleanCsvPath(name: CsvName): string {
    const p = path.join(HOST_OUT_DIR, name);
    if (! fs.existsSync(p)) {
        throw new Error(`Clean CSV not found at ${p}. Call generateFakeCsvs() first.`);
    }
    return p;
}

export type ParsedCsv = {
    headers: string[];
    rows: string[][];
};

export function parseCsv(filePath: string): ParsedCsv {
    const raw = fs.readFileSync(filePath, 'utf8');
    const lines = parseCsvLines(raw);
    const headers = lines.shift() ?? [];
    return { headers, rows: lines };
}

export function writeCsv(filePath: string, headers: string[], rows: string[][]): void {
    const out: string[] = [];
    out.push(headers.map(csvEscape).join(','));
    for (const row of rows) {
        out.push(row.map(csvEscape).join(','));
    }
    fs.writeFileSync(filePath, out.join('\n') + '\n');
}

export function tempCsvPath(name: string): string {
    const dir = path.join(HOST_OUT_DIR, 'tmp');
    if (! fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
    return path.join(dir, `${name}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}.csv`);
}

function csvEscape(value: string | null | undefined): string {
    if (value === null || value === undefined) return '';
    const s = String(value);
    if (s.includes(',') || s.includes('"') || s.includes('\n') || s.includes('\r')) {
        return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
}

function parseCsvLines(raw: string): string[][] {
    const lines: string[][] = [];
    let cur: string[] = [];
    let field = '';
    let inQuotes = false;
    for (let i = 0; i < raw.length; i++) {
        const c = raw[i];
        if (inQuotes) {
            if (c === '"') {
                if (raw[i + 1] === '"') { field += '"'; i++; }
                else { inQuotes = false; }
            } else {
                field += c;
            }
        } else {
            if (c === '"') inQuotes = true;
            else if (c === ',') { cur.push(field); field = ''; }
            else if (c === '\n') { cur.push(field); lines.push(cur); cur = []; field = ''; }
            else if (c === '\r') { /* skip */ }
            else field += c;
        }
    }
    if (field !== '' || cur.length > 0) { cur.push(field); lines.push(cur); }
    return lines.filter((r) => ! (r.length === 1 && r[0] === ''));
}
