import { test, expect } from '@playwright/test';
import zlib from 'node:zlib';
import { resetAndLogin } from '../helpers/auth.js';
import {
    findPageIdBySlug,
    createWidgetOnPage,
    setWidgetConfig,
    getWidgetConfig,
    deleteWidget,
} from '../helpers/db.js';

test.describe.configure({ mode: 'serial' });

// ── A guaranteed-valid 1×1 PNG (built with a real CRC + zlib IDAT) so the
// upload passes mime validation and any synchronous conversion can decode it. ──

function crc32(buf: Buffer): number {
    let c = 0xffffffff;
    for (let i = 0; i < buf.length; i++) {
        c ^= buf[i];
        for (let k = 0; k < 8; k++) c = (c >>> 1) ^ (0xedb88320 & -(c & 1));
    }
    return (c ^ 0xffffffff) >>> 0;
}

function pngChunk(type: string, data: Buffer): Buffer {
    const len = Buffer.alloc(4);
    len.writeUInt32BE(data.length, 0);
    const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(crc32(body), 0);
    return Buffer.concat([len, body, crc]);
}

function makePng(r: number, g: number, b: number): Buffer {
    const sig = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(1, 0); // width
    ihdr.writeUInt32BE(1, 4); // height
    ihdr[8] = 8; // bit depth
    ihdr[9] = 2; // color type: RGB
    const idat = zlib.deflateSync(Buffer.from([0, r, g, b])); // filter byte + RGB
    return Buffer.concat([
        sig,
        pngChunk('IHDR', ihdr),
        pngChunk('IDAT', idat),
        pngChunk('IEND', Buffer.alloc(0)),
    ]);
}

// An empty Image widget renders nothing (zero height — not selectable in the
// canvas). Seeding a placeholder src + a fixed ratio + width gives the widget a
// real rendered box to click, while leaving the inspector's upload/browse
// controls in place (image_urls is empty until a media row is actually attached).
const PLACEHOLDER_SRC = 'data:image/png;base64,' + makePng(200, 200, 200).toString('base64');

async function seedSelectablePlaceholder(widgetId: string): Promise<void> {
    await setWidgetConfig(widgetId, {
        image: PLACEHOLDER_SRC,
        aspect_ratio: '16:9',
        max_width: '400px',
    });
}

test.describe('Page builder — media picker (browse or upload)', () => {
    const createdWidgetIds: string[] = [];

    test.beforeAll(async ({ browser }) => {
        await resetAndLogin(browser);
    });

    test.afterAll(async () => {
        for (const id of createdWidgetIds) {
            await deleteWidget(id);
        }
        createdWidgetIds.length = 0;
    });

    test('uploads on one widget, then browses + picks that image on another', async ({ page }) => {
        const pageId = await findPageIdBySlug('home');
        expect(pageId).not.toBeNull();

        // Two Image widgets: A is the upload source (seeds the library), B is
        // the one we attach an existing image to via the browser.
        const widgetA = await createWidgetOnPage(pageId!, 'image');
        const widgetB = await createWidgetOnPage(pageId!, 'image');
        createdWidgetIds.push(widgetA, widgetB);
        await seedSelectablePlaceholder(widgetA);
        await seedSelectablePlaceholder(widgetB);

        await page.goto(`/admin/pages/${pageId}/edit`);
        await expect(page.locator('.preview-region[data-widget-id]').first()).toBeVisible({ timeout: 15_000 });

        // ── Upload still works: select A, upload a PNG via the file input. ──
        const regionA = page.locator(`.preview-region[data-widget-id="${widgetA}"]`);
        await regionA.locator('.preview-region__overlay').click();

        const fieldA = page.locator('.inspector-pane--top .image-upload').first();
        await expect(fieldA).toBeVisible();
        await fieldA.locator('.image-upload__input').setInputFiles({
            name: 'picker-source.png',
            mimeType: 'image/png',
            buffer: makePng(220, 40, 40),
        });

        // The upload set widget A's config.image to a media id.
        await expect.poll(async () => (await getWidgetConfig(widgetA)).image, { timeout: 15_000 })
            .toEqual(expect.any(Number));

        // ── Browse + pick: select B, open the library, pick the uploaded image. ──
        const regionB = page.locator(`.preview-region[data-widget-id="${widgetB}"]`);
        await regionB.locator('.preview-region__overlay').click();

        const fieldB = page.locator('.inspector-pane--top .image-upload').first();
        await expect(fieldB).toBeVisible();
        await fieldB.locator('.image-upload__browse').click();

        const browser = page.locator('.media-browser');
        await expect(browser).toBeVisible();

        // The just-uploaded image shows as a selectable thumbnail.
        const firstTile = browser.locator('.media-browser__tile').first();
        await expect(firstTile).toBeVisible({ timeout: 10_000 });
        await firstTile.click();

        // Modal closes and B now has an attached image id, persisted server-side.
        await expect(browser).toBeHidden();
        await expect.poll(async () => (await getWidgetConfig(widgetB)).image, { timeout: 15_000 })
            .toEqual(expect.any(Number));

        // ── Persistence: reload and confirm B's preview still renders. ──
        await page.reload();
        await expect(page.locator('.preview-region[data-widget-id]').first()).toBeVisible({ timeout: 15_000 });
        await page.locator(`.preview-region[data-widget-id="${widgetB}"]`).locator('.preview-region__overlay').click();
        await expect(
            page.locator('.inspector-pane--top .image-upload__preview img').first()
        ).toBeVisible({ timeout: 10_000 });
    });

    test('drives the in-modal search box (empties on a miss, restores on clear)', async ({ page }) => {
        // The filename filter itself is asserted server-side in
        // PageBuilderMediaListTest; here we prove the modal's search input drives
        // the endpoint and updates the grid. Uploaded files are stored under a
        // randomised hashName, so this stays filename-agnostic.
        const pageId = await findPageIdBySlug('home');
        const widgetC = await createWidgetOnPage(pageId!, 'image');
        createdWidgetIds.push(widgetC);
        await seedSelectablePlaceholder(widgetC);

        await page.goto(`/admin/pages/${pageId}/edit`);
        await expect(page.locator('.preview-region[data-widget-id]').first()).toBeVisible({ timeout: 15_000 });

        await page.locator(`.preview-region[data-widget-id="${widgetC}"]`).locator('.preview-region__overlay').click();
        const fieldC = page.locator('.inspector-pane--top .image-upload').first();
        await expect(fieldC).toBeVisible();
        await fieldC.locator('.image-upload__browse').click();

        const browser = page.locator('.media-browser');
        await expect(browser).toBeVisible();

        // The library has at least the image uploaded in the prior (serial) test.
        await expect(browser.locator('.media-browser__tile').first()).toBeVisible({ timeout: 10_000 });

        // A search miss empties the grid and shows the empty message.
        await browser.locator('.media-browser__search').fill('no-such-image-xyz');
        await expect(browser.locator('.media-browser__tile')).toHaveCount(0);
        await expect(browser.locator('.media-browser__message')).toBeVisible();

        // Clearing the search restores the results.
        await browser.locator('.media-browser__search').fill('');
        await expect(browser.locator('.media-browser__tile').first()).toBeVisible({ timeout: 10_000 });
    });
});
