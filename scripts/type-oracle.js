#!/usr/bin/env node
/**
 * Per-breakpoint typography oracle for the five published public pages
 * (session 295 — E5 Mobile Type Scaling).
 *
 * Loads each page at the named breakpoint widths (xl/lg/md/sm + phone) and
 * runs an in-page audit that returns a structured, machine-checkable
 * violation list. The ramp *values* are the user's tuning surface — this
 * oracle only holds the objective floor:
 *
 *   - page-overflow              documentElement scrolls horizontally
 *   - text-overflow              a text container's scrollWidth > clientWidth
 *   - body-below-floor           body/paragraph text computed < LEGIBLE_FLOOR px
 *   - heading-smaller-than-body  a content heading renders smaller than the
 *                                 page's body text
 *   - hierarchy-inversion        a lower-rank heading renders larger than a
 *                                 higher-rank one at this width (h1≥h2≥…≥h6)
 *
 * Process exits non-zero when any violation is found, so it works directly
 * as a remediation stop condition.
 *
 * Usage (run on the host, NOT inside Docker):
 *   node scripts/type-oracle.js
 *   node scripts/type-oracle.js --base-url=http://localhost
 *   node scripts/type-oracle.js --json-out=/tmp/type-oracle.json
 *   node scripts/type-oracle.js --screenshots=after
 *
 * Requirements (install once, globally):
 *   npm install --global playwright && npx playwright install chromium
 * If the global install is not visible to `require`, run with:
 *   NODE_PATH="$(npm root -g)" node scripts/type-oracle.js ...
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
const SHOT_DIR = path.join(PROJECT_ROOT, 'sessions', 'public website', 'type-oracle');

const PAGES = [
    { slug: 'home', urlPath: '/' },
    { slug: 'about', urlPath: '/about' },
    { slug: 'pricing', urlPath: '/pricing' },
    { slug: 'contact', urlPath: '/contact' },
    { slug: 'demo', urlPath: '/demo' },
];

// One width per tier of the compiler cascade (base ≥1200=xl; ≤992=lg;
// ≤768=md; ≤576=sm) plus a common phone width (also sm).
const WIDTHS = [1280, 980, 760, 540, 390];
const HEIGHT = 900;

const LEGIBLE_FLOOR = 12; // px — body text must never render below this

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
function inPageAudit(floor) {
    const SLACK = 1;
    const out = [];
    const vw = window.innerWidth;

    const cssPath = (el) => {
        if (!el || el.nodeType !== 1) return '';
        const parts = [];
        let node = el;
        for (let depth = 0; node && node.nodeType === 1 && depth < 5; depth++) {
            let seg = node.tagName.toLowerCase();
            if (node.id) { seg += `#${node.id}`; parts.unshift(seg); break; }
            const cls = (node.getAttribute('class') || '')
                .split(/\s+/).filter(Boolean).slice(0, 2).join('.');
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

    // Content text only — the compiler scopes to :not(nav …); mirror that by
    // excluding site chrome so the oracle judges body content, not the logo.
    const isContent = (el) =>
        !el.closest('header, nav, footer, [role=banner], [role=contentinfo]') &&
        !el.closest('.phpdebugbar, #phpdebugbar') &&
        !el.closest('[aria-hidden="true"]');

    const fs = (el) => parseFloat(getComputedStyle(el).fontSize) || 0;
    const hasOwnText = (el) =>
        Array.from(el.childNodes).some(
            (n) => n.nodeType === 3 && n.textContent.trim().length >= 3,
        );

    // ── page-overflow ────────────────────────────────────────────────────────
    const docW = document.documentElement.scrollWidth;
    if (docW - vw > 2) {
        out.push({ type: 'page-overflow', selector: 'html', detail: { scrollWidth: docW, innerWidth: vw } });
    }

    // ── text-overflow ────────────────────────────────────────────────────────
    const TEXT_TAGS = new Set([
        'P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'LI', 'SPAN', 'A',
        'BLOCKQUOTE', 'FIGCAPTION', 'TD', 'TH', 'LABEL', 'BUTTON', 'STRONG', 'EM',
    ]);
    for (const el of document.querySelectorAll('body *')) {
        if (!TEXT_TAGS.has(el.tagName)) continue;
        if (!isContent(el) || !visible(el)) continue;
        const s = getComputedStyle(el);
        if (s.overflowX === 'auto' || s.overflowX === 'scroll') continue;
        if (el.scrollWidth - el.clientWidth > 2 && el.clientWidth > 0) {
            out.push({
                type: 'text-overflow',
                selector: cssPath(el),
                detail: { scrollWidth: el.scrollWidth, clientWidth: el.clientWidth },
            });
        }
    }

    // ── body font-size reference (median of content <p> own-text sizes) ───────
    const pSizes = [];
    for (const p of document.querySelectorAll('p')) {
        if (!isContent(p) || !visible(p) || !hasOwnText(p)) continue;
        pSizes.push(fs(p));
    }
    pSizes.sort((a, b) => a - b);
    const bodyFs = pSizes.length ? pSizes[Math.floor(pSizes.length / 2)] : null;

    // ── body-below-floor ─────────────────────────────────────────────────────
    for (const el of document.querySelectorAll('p, li')) {
        if (!isContent(el) || !visible(el) || !hasOwnText(el)) continue;
        const size = fs(el);
        if (size > 0 && size < floor - SLACK) {
            out.push({
                type: 'body-below-floor',
                selector: cssPath(el),
                detail: { fontSize: +size.toFixed(2), floor },
            });
        }
    }

    // ── heading-smaller-than-body + per-level sizes ──────────────────────────
    const levelMax = {}; // h1..h6 → largest content instance at this width
    for (let lvl = 1; lvl <= 6; lvl++) {
        for (const h of document.querySelectorAll('h' + lvl)) {
            if (!isContent(h) || !visible(h) || !hasOwnText(h)) continue;
            const size = fs(h);
            levelMax[lvl] = Math.max(levelMax[lvl] ?? 0, size);
            if (bodyFs !== null && size < bodyFs - SLACK) {
                out.push({
                    type: 'heading-smaller-than-body',
                    selector: cssPath(h),
                    detail: { headingFs: +size.toFixed(2), bodyFs: +bodyFs.toFixed(2) },
                });
            }
        }
    }

    // ── hierarchy-inversion (monotonic non-increasing across present levels) ──
    const present = Object.keys(levelMax).map(Number).sort((a, b) => a - b);
    for (let i = 0; i < present.length - 1; i++) {
        const hi = present[i];
        const lo = present[i + 1];
        if (levelMax[lo] - levelMax[hi] > SLACK) {
            out.push({
                type: 'hierarchy-inversion',
                selector: `h${hi} vs h${lo}`,
                detail: { [`h${hi}`]: +levelMax[hi].toFixed(2), [`h${lo}`]: +levelMax[lo].toFixed(2) },
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
            console.error('  npm install --global playwright && npx playwright install chromium');
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
                isMobile: width < 992,
                hasTouch: width < 992,
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
                const found = await page.evaluate(inPageAudit, LEGIBLE_FLOOR);
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

    const byType = {};
    for (const v of report.violations) byType[v.type] = (byType[v.type] || 0) + 1;
    const summary = Object.entries(byType).map(([t, n]) => `${t}=${n}`).join(' ') || 'none';
    console.error(`\nType oracle @ ${args.baseUrl} — ${report.violations.length} violation(s): ${summary}`);
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
