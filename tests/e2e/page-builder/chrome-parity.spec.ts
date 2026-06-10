import { test, expect } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import { findPageIdBySlug } from '../helpers/db.js';
import * as path from 'node:path';

// Standing guard for the editor↔public chrome drift class (session 354).
//
// The page-builder preview renders the SAME server-produced header/footer HTML
// the public site does (ChromeRenderer), but it used to drop the public
// layout's wrapper structure — `.site-nav-wrapper > header.site-header` and
// `footer.site-footer` — so every chrome style keyed to those selectors failed
// to attach and the editor chrome drifted from the front end. This spec pins
// the editor's reproduction of those wrappers so the drift cannot silently
// return. The `.preview-chrome` hover/edit affordance must survive as the outer
// layer. Screenshots (editor at each preset viewport + the public render) are
// emitted for the visual eye but are not the assertion surface.

test.describe.configure({ mode: 'serial' });

const SHOTS = path.resolve(process.cwd(), 'test-results/chrome-parity');

test.describe('Page-builder chrome parity — editor reproduces the public wrappers', () => {
    let pageId: string;

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        const id = await findPageIdBySlug('home');
        if (!id) throw new Error('home page not found');
        pageId = id;
    });

    test('editor header/footer bands reproduce the public .site-nav-wrapper / .site-header / .site-footer structure', async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (err) => pageErrors.push(err.message));

        await page.goto(`/admin/pages/${pageId}/edit`);

        // ── Header band: .preview-chrome--header > .site-nav-wrapper >
        //    header.site-header > .preview-chrome__html (mirrors public.blade) ──
        const headerBand = page.locator('.preview-chrome--header');
        await expect(headerBand).toBeAttached({ timeout: 20_000 });
        await expect(
            headerBand.locator('.site-nav-wrapper > header.site-header > .preview-chrome__html')
        ).toBeAttached();
        // Affordance preserved: the "Edit header ↗" deep-link stays the outer layer.
        await expect(headerBand.locator('.preview-chrome__edit')).toBeAttached();

        // ── Footer band: .preview-chrome--footer > footer.site-footer >
        //    .preview-chrome__html ──
        const footerBand = page.locator('.preview-chrome--footer');
        await expect(footerBand).toBeAttached();
        await expect(
            footerBand.locator('footer.site-footer > .preview-chrome__html')
        ).toBeAttached();
        await expect(footerBand.locator('.preview-chrome__edit')).toBeAttached();

        // The reproduced .site-nav-wrapper attaches the public chrome style — a
        // non-transparent header band (white in the default light theme). Before
        // the fix the wrapper was absent and the band had no background of its
        // own; this is the objective "the style now attaches" signal.
        const navWrapperBg = await headerBand
            .locator('.site-nav-wrapper')
            .evaluate((el) => getComputedStyle(el).backgroundColor);
        expect(navWrapperBg).not.toBe('rgba(0, 0, 0, 0)');
        expect(navWrapperBg).not.toBe('transparent');

        // ── Visual capture for the owner's eye: editor chrome at each preset ──
        for (const vp of ['Desktop', 'Tablet', 'Mobile']) {
            await page.getByRole('button', { name: new RegExp(`^${vp} viewport`) }).click();
            await page.waitForTimeout(250);
            await headerBand.screenshot({ path: `${SHOTS}/editor-header-${vp.toLowerCase()}.png` });
            await footerBand.screenshot({ path: `${SHOTS}/editor-footer-${vp.toLowerCase()}.png` });
        }

        expect(pageErrors, `uncaught page errors:\n${pageErrors.join('\n')}`).toHaveLength(0);
    });

    test('public render of the same page exposes the source-of-truth chrome (visual reference)', async ({ page }) => {
        await page.goto('/');
        const publicHeader = page.locator('.site-nav-wrapper header.site-header').first();
        const publicFooter = page.locator('footer.site-footer').first();
        await expect(publicHeader).toBeAttached({ timeout: 10_000 });
        await expect(publicFooter).toBeAttached();
        await publicHeader.screenshot({ path: `${SHOTS}/public-header.png` });
        await publicFooter.screenshot({ path: `${SHOTS}/public-footer.png` });
    });

    test('chrome nav link colour matches the public render (Filament leak stays layered)', async ({ page }) => {
        // The chrome footer nav links get their colour from the widget rule
        // .widget-nav__column-link in @layer widgets. Filament's admin reset
        // (`a { color: inherit }`) is unlayered by default and would beat that
        // layered rule inside the .np-site preview, collapsing the link to the
        // inherited admin grey. resources/css/filament/admin/theme.css imports
        // Filament into `layer(filament)` (below @layer widgets) so the widget
        // rule wins again — editor link == public link. This guards that fix:
        // drop the layer() and the editor link reverts to the inherited grey.
        const linkColor = (sel: string) =>
            page.locator(sel).first().evaluate((el) => getComputedStyle(el as HTMLElement).color);

        await page.goto(`/admin/pages/${pageId}/edit`);
        await page.locator('.preview-chrome--footer footer.site-footer nav a').first()
            .waitFor({ state: 'attached', timeout: 20_000 });
        const editorLink = await linkColor('.preview-chrome--footer footer.site-footer nav a');
        const editorLi = await linkColor('.preview-chrome--footer footer.site-footer nav li');

        await page.goto('/');
        await page.locator('footer.site-footer nav a').first()
            .waitFor({ state: 'attached', timeout: 10_000 });
        const publicLink = await linkColor('footer.site-footer nav a');

        // The widget link colour now wins in the editor exactly as on public…
        expect(editorLink).toBe(publicLink);
        // …and is no longer just the inherited admin text colour (the leak symptom).
        expect(editorLink).not.toBe(editorLi);
    });
});
