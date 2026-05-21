// DIAGNOSTIC (temporary, A003).
// Reads a Playwright JSON report and emits a compact markdown digest:
// overall counts, every non-passing spec with its first error line and
// duration, and the slowest specs (to expose a uniform-timeout signature).
// Removed before the branch is handed back for review.

import { readFileSync } from 'node:fs';

const jsonPath = process.argv[2];

function loadReport(p) {
    try {
        return JSON.parse(readFileSync(p, 'utf8'));
    } catch (e) {
        return null;
    }
}

function collectSpecs(node, acc) {
    if (!node) return;
    for (const suite of node.suites ?? []) collectSpecs(suite, acc);
    for (const spec of node.specs ?? []) acc.push(spec);
}

function firstErrorLine(test) {
    for (const r of test.results ?? []) {
        const msg = r.error?.message ?? (r.errors ?? [])[0]?.message;
        if (msg) {
            return String(msg)
                .replace(/\[[0-9;]*m/g, '')
                .split('\n')
                .map((s) => s.trim())
                .filter(Boolean)
                .slice(0, 3)
                .join(' / ')
                .slice(0, 300);
        }
    }
    return '';
}

function maxDuration(test) {
    let d = 0;
    for (const r of test.results ?? []) d = Math.max(d, r.duration ?? 0);
    return d;
}

const report = loadReport(jsonPath);

if (!report) {
    console.log('# Playwright digest\n\n**No JSON report found** at `' + jsonPath + '` — Playwright likely crashed before writing results. See `stdout.txt`.');
    process.exit(0);
}

const rootSuites = report.suites ?? [];
const specs = [];
for (const s of rootSuites) collectSpecs(s, specs);

const rows = [];
for (const spec of specs) {
    for (const test of spec.tests ?? []) {
        rows.push({
            file: spec.file ?? '',
            line: spec.line ?? 0,
            title: spec.title ?? '',
            status: test.status ?? 'unknown', // expected | unexpected | flaky | skipped
            durMs: maxDuration(test),
            error: test.status === 'expected' ? '' : firstErrorLine(test),
        });
    }
}

const stats = report.stats ?? {};
const counts = { expected: 0, unexpected: 0, flaky: 0, skipped: 0, unknown: 0 };
for (const r of rows) counts[r.status] = (counts[r.status] ?? 0) + 1;

const fmt = (ms) => (ms / 1000).toFixed(1) + 's';

let out = '';
out += '# Playwright diagnostic digest (A003)\n\n';
out += `- total specs run: **${rows.length}**\n`;
out += `- passed: **${counts.expected}**  ·  failed: **${counts.unexpected}**  ·  flaky: **${counts.flaky}**  ·  skipped: **${counts.skipped}**\n`;
if (stats.duration != null) out += `- wall time: **${fmt(stats.duration)}**\n`;
out += '\n';

const failing = rows
    .filter((r) => r.status === 'unexpected' || r.status === 'flaky')
    .sort((a, b) => b.durMs - a.durMs);

out += `## Non-passing specs (${failing.length})\n\n`;
if (failing.length === 0) {
    out += '_none — suite green_\n\n';
} else {
    out += '| dur | status | spec | error (first lines) |\n';
    out += '|----:|:------|:-----|:--------------------|\n';
    for (const r of failing) {
        const loc = `${r.file}:${r.line}`;
        const t = r.title.replace(/\|/g, '\\|').slice(0, 80);
        const e = r.error.replace(/\|/g, '\\|');
        out += `| ${fmt(r.durMs)} | ${r.status} | \`${loc}\` — ${t} | ${e} |\n`;
    }
    out += '\n';
}

const slowest = [...rows].sort((a, b) => b.durMs - a.durMs).slice(0, 15);
out += '## 15 slowest specs (uniform-timeout signature check)\n\n';
out += '| dur | status | spec |\n|----:|:------|:-----|\n';
for (const r of slowest) {
    out += `| ${fmt(r.durMs)} | ${r.status} | \`${r.file}:${r.line}\` — ${r.title.replace(/\|/g, '\\|').slice(0, 80)} |\n`;
}
out += '\n';

console.log(out);
