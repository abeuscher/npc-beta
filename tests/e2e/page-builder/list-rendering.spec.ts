// Session 350 — standing CI guard for public list rendering.
//
// The page builder's Quill editor represents a bullet list as
// <ol><li data-list="bullet"> (Quill v2 quirk); WidgetRenderer's RichTextSemantics
// transform rewrites it to a real <ul> on the public render
// (App\Support\RichTextSemantics). The HTML-structure transform is Pest-covered
// (tests/Feature/RichTextSemanticsTest.php). The browser-only seam this guards is
// that the rewritten <ul> actually PAINTS a bullet marker under the real public
// stylesheet — the "lists render with the wrong / no markers" regression the
// owner reported as breaking regularly (housekeeping inbox [0.314.01]). An ordered
// list must stay an <ol>.
//
// The widget is removed in afterAll so the row does not leak into the next
// manual-test session.

import { test, expect } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import {
    findPageIdBySlug,
    createWidgetOnPage,
    setWidgetConfig,
    deleteWidget,
} from '../helpers/db.js';

test.describe.configure({ mode: 'serial' });

test.describe('Public list rendering — bullet <ol> normalises to a real <ul>', () => {
    let pageId: string;
    let widgetId: string;

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        const id = await findPageIdBySlug('home');
        if (!id) throw new Error('home page not found');
        pageId = id;
        widgetId = await createWidgetOnPage(pageId, 'text_block');

        // Quill-shaped content, exactly as the editor stores it: a bullet list
        // (every <li data-list="bullet">) followed by an ordered list
        // (every <li data-list="ordered">), both inside <ol> wrappers.
        await setWidgetConfig(widgetId, {
            content:
                '<ol><li data-list="bullet">Bullet one</li><li data-list="bullet">Bullet two</li></ol>' +
                '<ol><li data-list="ordered">Step one</li><li data-list="ordered">Step two</li></ol>',
            vertical_align: 'middle',
        });
    });

    test.afterAll(async () => {
        if (widgetId) await deleteWidget(widgetId);
    });

    test('bullet list paints a real <ul> with a disc marker; ordered stays <ol>', async ({ page }) => {
        await page.goto('/');

        const content = page.locator(`#widget-${widgetId} .widget-text-block__content`);
        await expect(content).toBeVisible({ timeout: 10_000 });

        // Structure: the bullet list is a real <ul> carrying no data-list; the
        // ordered list is left as an <ol>.
        const ul = content.locator('ul');
        const ol = content.locator('ol');
        await expect(ul).toHaveCount(1);
        await expect(ol).toHaveCount(1);
        await expect(content.locator('li[data-list="bullet"]')).toHaveCount(0);
        await expect(ul.locator('li').first()).toHaveText('Bullet one');
        await expect(ol.locator('li').first()).toHaveText('Step one');

        // Browser-only: under the real public stylesheet the <ul> paints a native
        // disc bullet (plain lists "fall through to native browser markers"), not
        // a suppressed/none marker — this is the regression Pest cannot see.
        const ulMarker = await ul.evaluate((el) => getComputedStyle(el).listStyleType);
        expect(ulMarker).toBe('disc');
    });
});
