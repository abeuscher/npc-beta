// Session 323 — standing CI guard for the universal border tool.
//
// Asserts the public render path emits the inline border styles produced by
// AppearanceStyleComposer onto the widget's outer box, and that the browser
// resolves them to the expected computed border-* values. Inline appearance
// styles are emitted by the renderer regardless of whether the widget bundle
// has been built, so this guard is CI-viable inside the isolated e2e stack
// (unlike widget-bundle CSS — see widget-color-tokens.spec.ts).
//
// The guard is intentionally narrow: one bordered Text widget on the seeded
// home page, one set of computed-value assertions covering width, style,
// colour, and radius. The widget is removed in afterAll so the row does not
// leak into the next manual-test session.

import { test, expect } from '@playwright/test';
import { resetAndLogin } from './helpers/auth.js';
import {
    findPageIdBySlug,
    getPageSlug,
    createWidgetOnPage,
    deleteWidget,
    setWidgetConfig,
} from './helpers/db.js';

test.describe.configure({ mode: 'serial' });

test.describe('Universal border — Text widget public render', () => {
    let pageId: string;
    let widgetId: string | null = null;

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        const id = await findPageIdBySlug('home');
        if (!id) throw new Error('home page not found');
        pageId = id;
    });

    test.afterAll(async () => {
        if (widgetId) {
            await deleteWidget(widgetId);
            widgetId = null;
        }
    });

    test('renders a bordered Text widget with the expected computed border-*', async ({ page }) => {
        widgetId = await createWidgetOnPage(pageId, 'text_block', {
            layout: {
                border: {
                    top: true,
                    right: true,
                    bottom: true,
                    left: true,
                    width: 2,
                    color: '#336699',
                    radius: 4,
                },
            },
        });
        await setWidgetConfig(widgetId, { content: '<p>Border probe</p>' });

        const slug = await getPageSlug(pageId);
        const url = slug === 'home' ? '/' : `/${slug}`;
        await page.goto(url);

        const widgetEl = page.locator(`#widget-${widgetId}`);
        await expect(widgetEl).toBeAttached({ timeout: 10_000 });

        const computed = await widgetEl.evaluate((el) => {
            const cs = getComputedStyle(el);
            return {
                topWidth:    cs.borderTopWidth,
                rightWidth:  cs.borderRightWidth,
                bottomWidth: cs.borderBottomWidth,
                leftWidth:   cs.borderLeftWidth,
                topStyle:    cs.borderTopStyle,
                topColor:    cs.borderTopColor,
                radius:      cs.borderTopLeftRadius,
                boxSizing:   cs.boxSizing,
            };
        });

        expect(computed.topWidth).toBe('2px');
        expect(computed.rightWidth).toBe('2px');
        expect(computed.bottomWidth).toBe('2px');
        expect(computed.leftWidth).toBe('2px');
        expect(computed.topStyle).toBe('solid');
        expect(computed.topColor).toBe('rgb(51, 102, 153)');
        expect(computed.radius).toBe('4px');
        expect(computed.boxSizing).toBe('border-box');
    });
});
