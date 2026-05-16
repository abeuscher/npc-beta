import { execFileSync } from 'node:child_process';
import path from 'node:path';
import fs from 'node:fs';

let pw;
try {
    pw = await import('playwright');
} catch (err) {
    const globalRoot = execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim();
    pw = await import(path.join(globalRoot, 'playwright', 'index.js'));
}
const chromium = pw.chromium ?? pw.default?.chromium;

const SITES = [
    { key: 'gong',  url: 'https://gong.io' },
    { key: 'attio', url: 'https://attio.com/' },
    { key: 'clay',  url: 'https://www.clay.com/' },
];
// phone + our named breakpoints (sm/md/lg/xl/xxl) + full desktop
const WIDTHS = [390, 576, 768, 992, 1200, 1400, 1920];
const OUT = '/tmp/type-calibration';
const UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';

// Markup-agnostic classifier. Marketing sites mark eyebrows as <h2> and skip
// <h3>, so tag names are unreliable. Classify by computed font-size clusters
// over true text leaves instead:
//   body    = mode font-size among long-paragraph leaves (most reliable signal)
//   display = largest font-size among headline-length leaves, >= body*1.25
//   section = the recurring (mode) size strictly between body*1.2 and display
function extract() {
    const inChrome = (el) => !!el.closest('header,nav,footer,[role=banner],[role=contentinfo],[aria-hidden="true"]');
    const visible = (el) => {
        const s = getComputedStyle(el), r = el.getBoundingClientRect();
        return s.display !== 'none' && s.visibility !== 'hidden' && s.opacity !== '0' && r.width > 4 && r.height > 4;
    };
    const ownText = (el) => {
        let t = '';
        for (const n of el.childNodes) if (n.nodeType === 3) t += n.textContent;
        return t.trim().replace(/\s+/g, ' ');
    };
    const leaves = [];
    for (const el of document.querySelectorAll('h1,h2,h3,h4,h5,h6,p,li,blockquote,figcaption,span,a,strong,div')) {
        if (inChrome(el) || !visible(el)) continue;
        const t = ownText(el);
        if (t.length < 3) continue;
        const cs = getComputedStyle(el);
        const fs = Math.round(parseFloat(cs.fontSize) * 2) / 2;
        if (!fs || fs < 8) continue;
        const lhRaw = cs.lineHeight;
        const lh = lhRaw === 'normal' ? null : Math.round(parseFloat(lhRaw) * 10) / 10;
        const mb = Math.round(parseFloat(cs.marginBottom) || 0);
        const pb = Math.round(parseFloat(cs.paddingBottom) || 0);
        leaves.push({ fs, lh, mb, pb, len: t.length, words: t.split(' ').length, t: t.slice(0, 50) });
    }
    if (!leaves.length) return {};
    const modeOf = (arr) => {
        const b = {};
        for (const v of arr) b[v] = (b[v] || 0) + 1;
        return +Object.entries(b).sort((a, c) => c[1] - a[1])[0][0];
    };
    const longs = leaves.filter((l) => l.len >= 60 && l.words >= 8);
    const bodyFs = longs.length ? modeOf(longs.map((l) => l.fs)) : modeOf(leaves.filter((l) => l.len >= 25).map((l) => l.fs));
    const body = longs.find((l) => l.fs === bodyFs) || leaves.find((l) => l.fs === bodyFs);

    const headlineish = leaves.filter((l) => l.len >= 3 && l.len <= 120 && l.fs >= bodyFs * 1.25);
    let display = null;
    for (const l of headlineish) if (!display || l.fs > display.fs) display = l;

    const sectionPool = headlineish.filter((l) => display && l.fs < display.fs && l.fs > bodyFs * 1.2);
    let section = null;
    if (sectionPool.length) {
        const sFs = modeOf(sectionPool.map((l) => l.fs));
        section = sectionPool.find((l) => l.fs === sFs);
    }
    const pack = (o) => o ? { fs: o.fs, lh: o.lh, mb: o.mb, pb: o.pb, t: o.t } : null;
    return { display: pack(display), section: pack(section), body: pack(body) };
}

const browser = await chromium.launch();
for (const { key, url } of SITES) {
    fs.mkdirSync(`${OUT}/${key}`, { recursive: true });
    const rows = [];
    for (const w of WIDTHS) {
        const ctx = await browser.newContext({ viewport: { width: w, height: 1000 }, deviceScaleFactor: 1, userAgent: UA });
        const page = await ctx.newPage();
        try {
            await page.goto(url, { waitUntil: 'load', timeout: 60000 });
            await page.evaluate(() => (document.fonts ? document.fonts.ready : 0));
            await page.waitForTimeout(1600);
            try {
                const btn = await page.$('#onetrust-accept-btn-handler, button:has-text("Accept all"), button:has-text("Accept"), button:has-text("Agree"), button:has-text("Allow all")');
                if (btn) { await btn.click({ timeout: 2000 }); await page.waitForTimeout(700); }
            } catch (e) {}
            const data = await page.evaluate(extract);
            await page.screenshot({ path: `${OUT}/${key}/${key}-${w}.png`, type: 'png' });
            rows.push({ w, ...data });
        } catch (e) {
            rows.push({ w, err: e.message });
        }
        await ctx.close();
    }
    fs.writeFileSync(`${OUT}/${key}/measurements.json`, JSON.stringify(rows, null, 2));
    const cell = (o) => o ? `${String(o.fs).padStart(5)}/${String(o.lh ?? '-').padStart(4)}` : '   --    ';
    console.log(`\n=== ${key} ===   (fontSize/lineHeight px)`);
    console.log(' width | display   | section   |  body');
    console.log('-------+-----------+-----------+----------');
    for (const r of rows) {
        if (r.err) { console.log(`${String(r.w).padStart(5)}  ERR ${r.err.slice(0, 48)}`); continue; }
        console.log(`${String(r.w).padStart(5)} | ${cell(r.display)} | ${cell(r.section)} | ${cell(r.body)}`);
    }
}
await browser.close();

// ── Average per-class ratio curves (each site normalized to its own 1920) ────
const classes = ['display', 'section', 'body'];
const acc = {};
for (const c of classes) acc[c] = {};
for (const { key } of SITES) {
    const rows = JSON.parse(fs.readFileSync(`${OUT}/${key}/measurements.json`, 'utf8'));
    const base = rows.find((r) => r.w === 1920) || rows[rows.length - 1];
    for (const c of classes) {
        const b = base[c]?.fs;
        if (!b) continue;
        for (const r of rows) {
            if (!r[c]?.fs) continue;
            (acc[c][r.w] ??= []).push(r[c].fs / b);
        }
    }
}
console.log('\n\n=== AVERAGE ratio vs each site\'s own desktop (1920 = 1.00) ===');
console.log(' width | display | section |  body');
console.log('-------+---------+---------+--------');
for (const w of WIDTHS) {
    const avg = (c) => {
        const a = acc[c][w]; if (!a || !a.length) return '  --  ';
        return (a.reduce((x, y) => x + y, 0) / a.length).toFixed(3);
    };
    console.log(`${String(w).padStart(5)} |  ${avg('display')}  |  ${avg('section')}  |  ${avg('body')}`);
}
console.log('\nn sites contributing per class @390:',
    classes.map((c) => `${c}=${(acc[c][390] || []).length}`).join('  '));

// ── Heading bottom-spacing as an em-ratio of the heading's own font-size ─────
// Stored relative to font-size so it auto-scales with the per-breakpoint ramp.
console.log('\n\n=== Heading bottom space (margin+padding) ÷ own font-size ===');
console.log('site  | display@390 disp@1920 | section@390 sec@1920');
console.log('------+-----------------------+---------------------');
const emAcc = { display: { 390: [], 1920: [] }, section: { 390: [], 1920: [] } };
for (const { key } of SITES) {
    const rows = JSON.parse(fs.readFileSync(`${OUT}/${key}/measurements.json`, 'utf8'));
    const em = (r, c) => {
        const o = r?.[c]; if (!o || !o.fs) return null;
        return ((o.mb || 0) + (o.pb || 0)) / o.fs;
    };
    const r390 = rows.find((r) => r.w === 390);
    const r1920 = rows.find((r) => r.w === 1920);
    const v = (x) => x == null ? '  --  ' : x.toFixed(2) + 'em';
    const d0 = em(r390, 'display'), d1 = em(r1920, 'display');
    const s0 = em(r390, 'section'), s1 = em(r1920, 'section');
    if (d0 != null) emAcc.display[390].push(d0);
    if (d1 != null) emAcc.display[1920].push(d1);
    if (s0 != null) emAcc.section[390].push(s0);
    if (s1 != null) emAcc.section[1920].push(s1);
    console.log(`${key.padEnd(5)} |   ${v(d0)}    ${v(d1)}  |   ${v(s0)}    ${v(s1)}`);
}
const mean = (a) => a.length ? (a.reduce((x, y) => x + y, 0) / a.length).toFixed(2) + 'em' : '--';
console.log('-----------------------------------------------------');
console.log(`MEAN  |   ${mean(emAcc.display[390])}    ${mean(emAcc.display[1920])}  |   ${mean(emAcc.section[390])}    ${mean(emAcc.section[1920])}`);
