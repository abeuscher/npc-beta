#!/usr/bin/env node
import { chromium } from 'playwright';

const URL = process.env.CONTACT_URL || 'http://localhost/contact';
const OUT = process.env.OUT_PATH || '/home/al/nonprofitcrm/sessions/public website/contact-after-rebuild-screenshot.png';

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
const page = await ctx.newPage();
await page.goto(URL, { waitUntil: 'networkidle' });
await page.waitForLoadState('domcontentloaded');
await page.evaluate(async () => {
    if (document.fonts && document.fonts.ready) await document.fonts.ready;
});
await page.waitForTimeout(500);
await page.screenshot({ path: OUT, fullPage: true });
await browser.close();
console.log('wrote', OUT);
