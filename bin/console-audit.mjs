// Console-error audit for the public site. Visits each page in a real headless
// browser and reports every console error/warning, uncaught page error, failed
// request, and 4xx/5xx response. Zero output lines per page = presentable.
//
// Usage:
//   node bin/console-audit.mjs                          # local stack, default page set
//   BASE=https://nphelper.com node bin/console-audit.mjs # production
//   node bin/console-audit.mjs / /pricing /donate        # explicit page list
//
// Read-only page visits — safe against production. Requires the repo's
// installed Playwright (run from the repo root). Exit code 1 if any page
// reported an issue, so it can gate CI or a deploy check.

import { chromium } from 'playwright';

const BASE = process.env.BASE ?? 'http://localhost';
const DEFAULT_PAGES = ['/', '/about', '/contact', '/demo', '/docs', '/pricing', '/privacy-policy', '/terms-of-use'];
const pages = process.argv.slice(2).length ? process.argv.slice(2) : DEFAULT_PAGES;

const browser = await chromium.launch();
let totalIssues = 0;

for (const path of pages) {
  const page = await browser.newPage();
  const entries = [];

  page.on('console', (msg) => {
    if (['error', 'warning'].includes(msg.type())) {
      entries.push({ kind: 'console.' + msg.type(), text: msg.text().slice(0, 500) });
    }
  });
  page.on('pageerror', (err) => entries.push({ kind: 'pageerror', text: String(err).slice(0, 500) }));
  page.on('requestfailed', (req) =>
    entries.push({ kind: 'requestfailed', text: req.url().slice(0, 200) + ' — ' + (req.failure()?.errorText ?? '?') }));
  page.on('response', (res) => {
    if (res.status() >= 400) entries.push({ kind: 'http' + res.status(), text: res.url().slice(0, 200) });
  });

  try {
    await page.goto(BASE + path, { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(2000);
  } catch (e) {
    entries.push({ kind: 'navigation', text: String(e).slice(0, 300) });
  }

  console.log('\n═══ ' + path + ' — ' + entries.length + ' issue(s)');
  for (const e of entries) console.log('  [' + e.kind + '] ' + e.text);
  totalIssues += entries.length;
  await page.close();
}

await browser.close();
console.log('\nTotal: ' + totalIssues + ' issue(s) across ' + pages.length + ' page(s).');
process.exit(totalIssues > 0 ? 1 : 0);
