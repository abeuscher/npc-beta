#!/usr/bin/env node
/**
 * Mobile-collapse audit oracle for the five published public marketing pages.
 *
 * Loads each page at a set of mobile viewport widths and runs an in-page audit
 * that returns a structured, machine-checkable violation list:
 *
 *   - page-overflow          documentElement scrolls horizontally
 *   - element-overflow       an element's box pokes past the viewport right edge
 *                             (root offenders only — parent does not overflow)
 *   - columns-not-collapsed  a .layout-grid[data-collapse-mobile="true"] still
 *                             has its .layout-column children side-by-side
 *   - text-overflow          a text container's scrollWidth exceeds clientWidth
 *   - aspect-distorted       an element with a declared aspect-ratio renders at
 *                             a materially different ratio
 *
 * The process exits non-zero when any violation is found, so it is usable
 * directly as the autonomous remediation loop's stop condition.
 *
 * Usage (run on the host, NOT inside Docker):
 *   node scripts/audit-mobile.js
 *   node scripts/audit-mobile.js --base-url=http://localhost:8080
 *   node scripts/audit-mobile.js --json-out=/tmp/audit.json
 *   node scripts/audit-mobile.js --screenshots=before     # also write PNGs
 *
 * Requirements (install once, globally):
 *   npm install --global playwright
 *   npx playwright install chromium
 *
 * If the global install is not visible to `require`, run with:
 *   NODE_PATH="$(npm root -g)" node scripts/audit-mobile.js ...
 *
 * Run on demand, not in CI.
 */

import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const PROJECT_ROOT = path.resolve(__dirname, '..');
const SHOT_DIR = path.join(PROJECT_ROOT, 'sessions', 'public website', 'mobile-audit');

const PAGES = [
    { slug: 'home', urlPath: '/' },
    { slug: 'about', urlPath: '/about' },
    { slug: 'pricing', urlPath: '/pricing' },
    { slug: 'contact', urlPath: '/contact' },
    { slug: 'demo', urlPath: '/demo' },
];

// 767 sits one px below the 768px collapse threshold so the audit asserts the
// collapsed state at the boundary; the rest are common phone widths.
const WIDTHS = [320, 360, 390, 414, 767];
const HEIGHT = 900;

function parseArgs(argv) {
    const args = { baseUrl: 'http://localhost', jsonOut: null, screenshots: null };
    for (const raw of argv.slice(2)) {
        if (raw.startsWith('--base-url=')) {
            args.baseUrl = raw.slice('--base-url='.length).replace(/\/$/, '');
        } else if (raw.startsWith('--json-out=')) {
            args.jsonOut = raw.slice('--json-out='.length);
        } else if (raw.startsWith('--screenshots=')) {
            args.screenshots = raw.slice('--screenshots='.length) || 'capture';
        }
    }
    return args;
}

// Runs in the page. Returns a flat array of violation objects for this width.
function inPageAudit() {
    const SLACK = 2;
    const out = [];
    const vw = window.innerWidth;

    const cssPath = (el) => {
        if (!el || el.nodeType !== 1) return '';
        const parts = [];
        let node = el;
        for (let depth = 0; node && node.nodeType === 1 && depth < 5; depth++) {
            let seg = node.tagName.toLowerCase();
            if (node.id) {
                seg += `#${node.id}`;
                parts.unshift(seg);
                break;
            }
            const cls = (node.getAttribute('class') || '')
                .split(/\s+/)
                .filter(Boolean)
                .slice(0, 2)
                .join('.');
            if (cls) seg += `.${cls}`;
            parts.unshift(seg);
            node = node.parentElement;
        }
        return parts.join(' > ');
    };

    const visible = (el) => {
        const s = getComputedStyle(el);
        if (s.display === 'none' || s.visibility === 'hidden' || s.opacity === '0') return false;
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0;
    };

    // ── page-overflow ────────────────────────────────────────────────────────
    const docW = document.documentElement.scrollWidth;
    if (docW - vw > SLACK) {
        out.push({ type: 'page-overflow', selector: 'html', detail: { scrollWidth: docW, innerWidth: vw } });
    }

    // ── element-overflow (root offenders only) ───────────────────────────────
    const overflowing = [];
    for (const el of document.querySelectorAll('body *')) {
        if (el.closest('.phpdebugbar, #phpdebugbar')) continue;
        if (el.closest('[aria-hidden="true"]')) continue; // swiper loop clones etc.
        if (!visible(el)) continue;
        const s = getComputedStyle(el);
        if (s.position === 'fixed' || s.position === 'sticky') continue;
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) continue;
        const over = Math.max(r.right - vw, -r.left);
        if (over > SLACK) overflowing.push({ el, over });
    }
    for (const { el, over } of overflowing) {
        const parent = el.parentElement;
        if (parent && parent !== document.body && parent !== document.documentElement) {
            const pr = parent.getBoundingClientRect();
            if (Math.max(pr.right - vw, -pr.left) > SLACK) continue; // not a root offender
        }
        out.push({
            type: 'element-overflow',
            selector: cssPath(el),
            detail: { overflowPx: Math.round(over) },
        });
    }

    // ── columns-not-collapsed ────────────────────────────────────────────────
    for (const grid of document.querySelectorAll('.layout-grid[data-collapse-mobile="true"]')) {
        const cols = Array.from(grid.children).filter(
            (c) => c.classList && c.classList.contains('layout-column')
        );
        if (cols.length < 2) continue;
        const rects = cols.map((c) => c.getBoundingClientRect()).filter((r) => r.height > 0);
        let sideBySide = false;
        for (let i = 0; i < rects.length && !sideBySide; i++) {
            for (let j = i + 1; j < rects.length; j++) {
                const a = rects[i];
                const b = rects[j];
                const vOverlap = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
                const differentX = Math.abs(a.left - b.left) > SLACK;
                if (vOverlap > 4 && differentX) {
                    sideBySide = true;
                    break;
                }
            }
        }
        if (sideBySide) {
            out.push({
                type: 'columns-not-collapsed',
                selector: cssPath(grid),
                detail: { columns: cols.length },
            });
        }
    }

    // ── text-overflow ────────────────────────────────────────────────────────
    const TEXT_TAGS = new Set([
        'P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'LI', 'SPAN', 'A', 'BLOCKQUOTE',
        'FIGCAPTION', 'TD', 'TH', 'LABEL', 'BUTTON', 'STRONG', 'EM',
    ]);
    for (const el of document.querySelectorAll('body *')) {
        if (!TEXT_TAGS.has(el.tagName)) continue;
        if (el.closest('.phpdebugbar, #phpdebugbar')) continue;
        if (el.closest('[aria-hidden="true"]')) continue;
        if (!visible(el)) continue;
        const s = getComputedStyle(el);
        if (s.overflowX === 'auto' || s.overflowX === 'scroll') continue;
        if (el.scrollWidth - el.clientWidth > SLACK && el.clientWidth > 0) {
            out.push({
                type: 'text-overflow',
                selector: cssPath(el),
                detail: { scrollWidth: el.scrollWidth, clientWidth: el.clientWidth },
            });
        }
    }

    // ── aspect-distorted ─────────────────────────────────────────────────────
    for (const el of document.querySelectorAll('body *')) {
        if (!visible(el)) continue;
        const s = getComputedStyle(el);
        const ar = s.aspectRatio;
        if (!ar || ar === 'auto' || ar === 'normal') continue;
        const m = ar.match(/([\d.]+)\s*\/\s*([\d.]+)/) || ar.match(/^([\d.]+)$/);
        if (!m) continue;
        const declared = m[2] ? parseFloat(m[1]) / parseFloat(m[2]) : parseFloat(m[1]);
        const r = el.getBoundingClientRect();
        if (r.width < 4 || r.height < 4 || !declared) continue;
        const rendered = r.width / r.height;
        if (Math.abs(rendered - declared) / declared > 0.08) {
            out.push({
                type: 'aspect-distorted',
                selector: cssPath(el),
                detail: { declared: +declared.toFixed(3), rendered: +rendered.toFixed(3) },
            });
        }
    }

    return out;
}

async function main() {
    const args = parseArgs(process.argv);

    let playwright;
    try {
        playwright = await import('playwright');
    } catch (err) {
        try {
            const globalRoot = execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim();
            const candidate = path.join(globalRoot, 'playwright', 'index.js');
            if (fs.existsSync(candidate)) {
                playwright = await import(candidate);
            } else {
                throw err;
            }
        } catch (inner) {
            console.error('Playwright is not installed. Install globally with:');
            console.error('  npm install --global playwright');
            console.error('  npx playwright install chromium');
            process.exit(1);
        }
    }

    const chromium = playwright.chromium ?? playwright.default?.chromium;
    if (!chromium) {
        console.error('Playwright module does not expose chromium — unexpected module shape.');
        process.exit(1);
    }

    const browser = await chromium.launch();
    const report = { baseUrl: args.baseUrl, generatedAt: new Date().toISOString(), violations: [] };
    let loadFailures = 0;

    if (args.screenshots) {
        fs.mkdirSync(path.join(SHOT_DIR, args.screenshots), { recursive: true });
    }

    for (const { slug, urlPath } of PAGES) {
        const url = `${args.baseUrl}${urlPath}`;
        for (const width of WIDTHS) {
            const context = await browser.newContext({
                viewport: { width, height: HEIGHT },
                deviceScaleFactor: 1,
                isMobile: true,
                hasTouch: true,
            });
            const page = await context.newPage();
            try {
                const response = await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
                if (!response || !response.ok()) {
                    loadFailures += 1;
                    report.violations.push({
                        page: slug, width, type: 'load-failure',
                        selector: '', detail: { status: response ? response.status() : 'no response' },
                    });
                    await context.close();
                    continue;
                }
                await page.evaluate(() => (document.fonts ? document.fonts.ready : Promise.resolve()));
                await page.evaluate(() => {
                    document.querySelectorAll('.phpdebugbar, #phpdebugbar').forEach((el) => el.remove());
                });
                await page.waitForTimeout(250);
                const found = await page.evaluate(inPageAudit);
                for (const v of found) report.violations.push({ page: slug, width, ...v });
                if (args.screenshots) {
                    await page.screenshot({
                        path: path.join(SHOT_DIR, args.screenshots, `${slug}-${width}.png`),
                        type: 'png',
                        fullPage: true,
                    });
                }
            } catch (err) {
                loadFailures += 1;
                report.violations.push({
                    page: slug, width, type: 'load-failure', selector: '',
                    detail: { error: err.message },
                });
            } finally {
                await context.close();
            }
        }
    }

    await browser.close();

    const json = JSON.stringify(report, null, 2);
    if (args.jsonOut) {
        fs.mkdirSync(path.dirname(args.jsonOut), { recursive: true });
        fs.writeFileSync(args.jsonOut, json);
    }

    // Summary to stderr (human), machine list to stdout (loop oracle).
    const byType = {};
    for (const v of report.violations) byType[v.type] = (byType[v.type] || 0) + 1;
    const summary = Object.entries(byType).map(([t, n]) => `${t}=${n}`).join(' ') || 'none';
    console.error(`\nMobile audit @ ${args.baseUrl} — ${report.violations.length} violation(s): ${summary}`);
    if (report.violations.length) {
        const sample = report.violations.slice(0, 40);
        for (const v of sample) {
            console.error(`  [${v.page} ${v.width}px] ${v.type} ${v.selector} ${JSON.stringify(v.detail)}`);
        }
        if (report.violations.length > sample.length) {
            console.error(`  … and ${report.violations.length - sample.length} more`);
        }
    }
    console.log(json);

    process.exit(report.violations.length > 0 || loadFailures > 0 ? 1 : 0);
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
