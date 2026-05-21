import { test, expect } from '@playwright/test';
import { resetAndLogin } from '../helpers/auth.js';
import {
    findPageIdBySlug,
    getPageSlug,
    createWidgetOnPage,
    deleteWidget,
    createLayoutOnPage,
    deleteLayout,
    updateLayoutConfig,
    updateLayoutAppearanceConfig,
} from '../helpers/db.js';

test.describe.configure({ mode: 'serial' });

type FullWidthState = { bg: boolean; content: boolean };

const STATES: { label: string; state: FullWidthState }[] = [
    { label: '(bg:false, content:false)', state: { bg: false, content: false } },
    { label: '(bg:true,  content:false)', state: { bg: true, content: false } },
    { label: '(bg:true,  content:true)',  state: { bg: true, content: true } },
];

const REPRESENTATIVE_WIDGETS = ['text_block', 'hero', 'nav'];

function appearanceForState(state: FullWidthState): Record<string, unknown> {
    return {
        layout: {
            background_full_width: state.bg,
            content_full_width: state.content,
        },
    };
}

async function publicUrlForPage(pageId: string): Promise<string> {
    const slug = await getPageSlug(pageId);
    if (!slug) throw new Error(`No slug for page ${pageId}`);
    return slug === 'home' ? '/' : `/${slug}`;
}

// ── Public-side widget matrix ───────────────────────────────────────────────

test.describe('Public-side — widget full-width matrix', () => {
    let pageId: string;
    let createdWidgetIds: string[] = [];

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        const id = await findPageIdBySlug('home');
        if (!id) throw new Error('home page not found');
        pageId = id;
    });

    test.afterAll(async () => {
        for (const id of createdWidgetIds) {
            await deleteWidget(id);
        }
        createdWidgetIds = [];
    });

    for (const handle of REPRESENTATIVE_WIDGETS) {
        for (const { label, state } of STATES) {
            test(`${handle} ${label} renders the expected wrapper structure`, async ({ page }) => {
                const widgetId = await createWidgetOnPage(pageId, handle, appearanceForState(state));
                createdWidgetIds.push(widgetId);

                const url = await publicUrlForPage(pageId);
                await page.goto(url);

                const widgetEl = page.locator(`#widget-${widgetId}`);
                await expect(widgetEl).toBeAttached({ timeout: 10_000 });

                const parentClass = await widgetEl.evaluate((el) => el.parentElement?.className ?? '');
                const directChildHasContainer = await widgetEl.evaluate((el) => {
                    return Array.from(el.children).some((c) => c.classList.contains('site-container'));
                });

                if (state.bg) {
                    // Outer site-container should NOT wrap the widget when bg is full-width.
                    expect(parentClass).not.toContain('site-container');
                } else {
                    // Outer site-container must wrap the widget.
                    expect(parentClass).toContain('site-container');
                }

                if (state.content) {
                    expect(directChildHasContainer).toBe(false);
                } else {
                    expect(directChildHasContainer).toBe(true);
                }
            });
        }
    }

    test('normalizes (bg:false, content:true) to bg:true semantics', async ({ page }) => {
        const widgetId = await createWidgetOnPage(
            pageId,
            'text_block',
            appearanceForState({ bg: false, content: true }),
        );
        createdWidgetIds.push(widgetId);

        await page.goto(await publicUrlForPage(pageId));
        const widgetEl = page.locator(`#widget-${widgetId}`);
        await expect(widgetEl).toBeAttached({ timeout: 10_000 });

        const parentClass = await widgetEl.evaluate((el) => el.parentElement?.className ?? '');
        const directChildHasContainer = await widgetEl.evaluate((el) =>
            Array.from(el.children).some((c) => c.classList.contains('site-container')),
        );

        // Renders as (bg:true, content:true): no wrappers.
        expect(parentClass).not.toContain('site-container');
        expect(directChildHasContainer).toBe(false);
    });
});

// ── Public-side column-layout matrix ────────────────────────────────────────

test.describe('Public-side — column-layout full-width matrix', () => {
    let pageId: string;
    let createdLayoutIds: string[] = [];

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        const id = await findPageIdBySlug('home');
        if (!id) throw new Error('home page not found');
        pageId = id;
    });

    test.afterAll(async () => {
        for (const id of createdLayoutIds) {
            await deleteLayout(id);
        }
        createdLayoutIds = [];
    });

    for (const { label, state } of STATES) {
        test(`column-layout ${label} renders the expected wrapper structure`, async ({ page }) => {
            const layoutId = await createLayoutOnPage(pageId);
            createdLayoutIds.push(layoutId);

            await updateLayoutConfig(layoutId, {
                grid_template_columns: '1fr 1fr',
                gap: '1rem',
                background_full_width: state.bg,
                content_full_width: state.content,
            });

            await page.goto(await publicUrlForPage(pageId));

            // The layout block emits as <div class="widget widget--page_layout">…
            const widgetEl = page.locator(`#widget-${layoutId}`);
            await expect(widgetEl).toBeAttached({ timeout: 10_000 });

            const pageLayoutEl = widgetEl.locator('.page-layout');
            await expect(pageLayoutEl).toBeAttached();

            // Outer site-container wraps .widget when bg is NOT full-width.
            const parentClass = await widgetEl.evaluate((el) => el.parentElement?.className ?? '');
            if (state.bg) {
                expect(parentClass).not.toContain('site-container');
            } else {
                expect(parentClass).toContain('site-container');
            }

            // Inside .page-layout: optional .site-container > .layout-grid
            const innerStructure = await pageLayoutEl.evaluate((el) => {
                const firstChild = el.children[0];
                return {
                    firstChildClass: firstChild?.className ?? '',
                    hasSiteContainer: firstChild?.classList.contains('site-container') ?? false,
                };
            });

            if (state.content) {
                expect(innerStructure.firstChildClass).toContain('layout-grid');
            } else {
                expect(innerStructure.hasSiteContainer).toBe(true);
                // .site-container > .layout-grid
                const gridInsideContainer = await pageLayoutEl
                    .locator('.site-container > .layout-grid')
                    .count();
                expect(gridInsideContainer).toBe(1);
            }
        });
    }
});

// ── Column-child clamp ──────────────────────────────────────────────────────

test.describe('Public-side — column-child widget clamp', () => {
    let pageId: string;
    let layoutId: string | null = null;
    let widgetId: string | null = null;

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        const id = await findPageIdBySlug('home');
        if (!id) throw new Error('home page not found');
        pageId = id;
    });

    test.afterAll(async () => {
        if (widgetId) await deleteWidget(widgetId);
        if (layoutId) await deleteLayout(layoutId);
    });

    test('widget inside a column with both knobs true is still clamped to (false, false)', async ({ page }) => {
        layoutId = await createLayoutOnPage(pageId);
        widgetId = await createWidgetOnPage(
            pageId,
            'text_block',
            { layout: { background_full_width: true, content_full_width: true } },
            layoutId,
            0,
        );

        await page.goto(await publicUrlForPage(pageId));

        // Column-child widget is rendered bare inside the layout's column track.
        // No outer or inner .site-container in the immediate ancestry.
        const widgetEl = page.locator(`#widget-${widgetId}`);
        await expect(widgetEl).toBeAttached({ timeout: 10_000 });

        const ancestry = await widgetEl.evaluate((el) => {
            const result: string[] = [];
            let cursor: Element | null = el.parentElement;
            // Walk up at most 3 ancestors.
            for (let i = 0; i < 3 && cursor; i++) {
                result.push(cursor.className || cursor.tagName);
                cursor = cursor.parentElement;
            }
            return result;
        });

        // Expected: parent is .layout-column; grandparent is .layout-grid (not .site-container).
        expect(ancestry[0]).toContain('layout-column');
        expect(ancestry.join(' ')).toContain('layout-grid');
    });
});

// ── Editor-side column bg parity ────────────────────────────────────────────

test.describe('Editor — column bg parity with public side', () => {
    let pageId: string;
    let layoutId: string | null = null;

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
        const id = await findPageIdBySlug('home');
        if (!id) throw new Error('home page not found');
        pageId = id;
    });

    test.afterAll(async () => {
        if (layoutId) await deleteLayout(layoutId);
        layoutId = null;
    });

    test('editor renders solid bg color set via DB on a column', async ({ page }) => {
        layoutId = await createLayoutOnPage(pageId);
        await updateLayoutAppearanceConfig(layoutId, {
            background: { color: '#ff8800' },
        });

        await page.goto(`/admin/pages/${pageId}/edit`);

        const layoutRegion = page.locator(`[data-layout-id="${layoutId}"]`);
        await expect(layoutRegion).toBeVisible({ timeout: 15_000 });

        const container = layoutRegion.locator('.layout-region__container');
        // Assert the render-time-composed inline style attribute, NOT
        // getComputedStyle: the layout background is delivered by the Vue
        // :style binding (appearance_config / API inline_style) — a
        // deliberately drift-proof request-time layer the build-server bundle
        // never touches. Reading el.style.* (inline only, no cascade) makes
        // this stale-bundle-proof by construction: a stale public/build can no
        // longer manufacture a false green here (the s296 incident class).
        const bg = await container.evaluate((el) => (el as HTMLElement).style.backgroundColor);
        // Inline background-color:#ff8800 serialises to rgb(255, 136, 0).
        expect(bg).toBe('rgb(255, 136, 0)');
    });

    test('editor renders gradient bg set via DB on a column', async ({ page }) => {
        if (layoutId) {
            await deleteLayout(layoutId);
            layoutId = null;
        }
        layoutId = await createLayoutOnPage(pageId);
        await updateLayoutAppearanceConfig(layoutId, {
            background: {
                gradient: {
                    gradients: [
                        { type: 'linear', from: '#a1c4fd', to: '#c2e9fb', angle: 180 },
                    ],
                },
            },
        });

        await page.goto(`/admin/pages/${pageId}/edit`);

        const layoutRegion = page.locator(`[data-layout-id="${layoutId}"]`);
        await expect(layoutRegion).toBeVisible({ timeout: 15_000 });

        const container = layoutRegion.locator('.layout-region__container');
        // Inline style only (see solid-color test above): the gradient is
        // composed into the element's style attribute via the API
        // inline_style / appearance binding, never the build-server bundle —
        // stale-bundle-proof by construction.
        const bgImage = await container.evaluate((el) => (el as HTMLElement).style.backgroundImage);
        expect(bgImage).toContain('linear-gradient');
    });
});

// The "every widget handle renders without console errors on the public
// site" smoke test was removed during e2e stabilization: it depends on the
// external widget build pipeline (public/build/widgets/) which the isolated
// e2e stack deliberately does not produce (no build server in CI, by
// recorded design). Per-widget public render is exercised by the
// representative-widget matrix above; the full all-widgets sweep remains a
// documented dev-box / manual check.
