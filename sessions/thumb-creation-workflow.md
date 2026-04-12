# Widget Thumbnail Generation System — Implementation Prompt

## Context

This is a Laravel application with a Blade/SCSS frontend. The app has a concept of "widgets" — modular building blocks used to compose web pages. There are currently ~30 widgets and growing. Each widget requires two thumbnail assets:

1. **Static thumbnail** — a PNG screenshot of the widget in its default/initial state
2. **Animated thumbnail** — a short looping video (MP4 or animated WebP) demonstrating the widget's appearance and interaction behavior

The goal is a **repeatable, scriptable build process** — not a manual screen recording workflow. Thumbnails must be regeneratable on demand, per-widget or in bulk, as widgets are added or updated over time.

---

## Architecture Overview

### 1. Isolated Widget Demo Routes (Laravel)

Create a set of gated dev-only routes that render each widget in a clean, controlled environment:

- Route pattern: `/dev/widgets/{slug}` (e.g. `/dev/widgets/event-calendar`)
- These routes should be protected by a middleware gate that restricts access to `local` and `staging` environments only (use `App::environment()` or a dedicated `WidgetDevMiddleware`)
- Each demo page renders the widget at a **fixed, predictable viewport** — no surrounding navigation, no header/footer, no page chrome
- Pages should be pre-seeded with realistic demo data (hardcoded or pulled from seeders) so the widget renders in a meaningful, visually complete state
- A single Blade layout (`layouts/widget-demo.blade.php`) should be created for this purpose — minimal HTML shell, full CSS/JS assets loaded, fixed body dimensions matching the capture viewport (default: `800px × 500px`)
- Each widget demo view lives at `resources/views/dev/widgets/{slug}.blade.php`

Register routes in a file that is conditionally loaded only in non-production environments, e.g. in `RouteServiceProvider` or via a conditional `require` in `routes/web.php`:

```php
if (App::environment(['local', 'staging'])) {
    require base_path('routes/dev.php');
}
```

---

### 2. Widget Manifest

Create a JSON manifest at `resources/widget-thumbnails/manifest.json`. This is the single source of truth for thumbnail generation. Structure:

```json
{
  "widgets": [
    {
      "slug": "event-calendar",
      "label": "Event Calendar",
      "url": "/dev/widgets/event-calendar",
      "viewport": { "width": 800, "height": 500 },
      "interaction": "scroll_month_forward",
      "interaction_duration_ms": 2400,
      "notes": "Hover over an event to show tooltip before advancing month"
    },
    {
      "slug": "donation-form",
      "label": "Donation Form",
      "url": "/dev/widgets/donation-form",
      "viewport": { "width": 800, "height": 500 },
      "interaction": "fill_and_submit",
      "interaction_duration_ms": 3000,
      "notes": null
    }
  ]
}
```

**Field definitions:**

- `slug` — kebab-case unique identifier, used as directory and filename
- `label` — human-readable name, for logging only
- `url` — full path to the demo route (no origin — the script prepends the base URL)
- `viewport` — width/height in pixels; can be overridden per widget if the widget requires a non-standard size
- `interaction` — string key referencing a named interaction function in the capture script (see below)
- `interaction_duration_ms` — how long the interaction recording runs before stopping; used to trim the output video
- `notes` — optional freeform string for developer context; not used programmatically

---

### 3. Playwright Capture Script

Create the script at `scripts/generate-thumbnails.js`. This is a standalone Node.js script (not a test runner) that uses Playwright directly.

#### Setup

```bash
npm install --save-dev playwright
npx playwright install chromium
```

Place the script outside of any test directories to make the intent clear.

#### Script Responsibilities

1. Parse CLI arguments (`--widget=slug`, `--all`, `--static-only`, `--animated-only`, `--base-url`)
2. Read and validate `manifest.json`
3. For each target widget:
   a. Launch Chromium at the specified viewport
   b. Navigate to the demo URL
   c. Wait for network idle and any CSS transitions to settle (`waitForLoadState('networkidle')` + optional fixed delay)
   d. Take a PNG screenshot → static thumbnail
   e. If animated: start Playwright's built-in video recording, execute the named interaction, stop recording
   f. Post-process video with ffmpeg (see below)
   g. Write output files to the correct paths
4. Log results to stdout with clear success/failure per widget

#### CLI Usage

```bash
# Generate everything
node scripts/generate-thumbnails.js --all

# Single widget
node scripts/generate-thumbnails.js --widget=event-calendar

# Static only (faster, for appearance-only changes)
node scripts/generate-thumbnails.js --all --static-only

# Override base URL (e.g. staging)
node scripts/generate-thumbnails.js --all --base-url=https://staging.yourapp.com
```

Default `--base-url` should be `http://localhost` (or whatever your local Valet/Herd domain is). Consider reading it from a `.env` value as a fallback.

#### Output Paths

```
public/img/widgets/thumbnails/{slug}/static.png
public/img/widgets/thumbnails/{slug}/animated.mp4
public/img/widgets/thumbnails/{slug}/animated.webp   (optional, generated alongside mp4)
```

The script should create these directories if they don't exist.

#### Playwright Video Configuration

Playwright's video recording is configured at the browser context level:

```javascript
const context = await browser.newContext({
  viewport: { width: widget.viewport.width, height: widget.viewport.height },
  recordVideo: {
    dir: tmpDir,
    size: { width: widget.viewport.width, height: widget.viewport.height }
  }
});
```

After the interaction completes, close the page (not the context) to finalize the video file, then retrieve the path via `page.video().path()`.

---

### 4. Interaction Library

Within the capture script, define a plain object mapping interaction keys to async functions. Each function receives a Playwright `page` object and the widget manifest entry:

```javascript
const INTERACTIONS = {

  // Default: do nothing (static-looking animation, maybe a subtle scroll)
  idle: async (page, widget) => {
    await page.waitForTimeout(widget.interaction_duration_ms);
  },

  // Hover over interactive elements sequentially
  hover_items: async (page, widget) => {
    const items = await page.locator('[data-widget-item]').all();
    for (const item of items) {
      await item.hover();
      await page.waitForTimeout(400);
    }
  },

  // Simulate a month-forward navigation on a calendar widget
  scroll_month_forward: async (page, widget) => {
    await page.waitForTimeout(500);
    await page.locator('[data-action="next-month"]').click();
    await page.waitForTimeout(widget.interaction_duration_ms - 500);
  },

  // Fill a form and hit submit (without actual submission — intercept or use a demo mode flag)
  fill_and_submit: async (page, widget) => {
    await page.locator('[data-field="amount"]').click();
    await page.locator('[data-field="amount"]').fill('50');
    await page.waitForTimeout(400);
    await page.locator('[data-field="email"]').fill('donor@example.org');
    await page.waitForTimeout(400);
    await page.locator('[data-submit]').click();
    await page.waitForTimeout(800);
  },

  // Add more as needed...
};
```

**Important:** Widget demo pages should render in a "demo mode" that suppresses real form submissions, API calls, or redirects. Consider passing a `?demo=1` query parameter that the Blade views and any JS components check before firing real actions. This keeps captures clean and repeatable.

---

### 5. ffmpeg Post-Processing

After Playwright saves the raw WebM video, shell-exec ffmpeg to produce the final formats. The script should check for ffmpeg availability at startup and exit with a clear error if it's missing.

```javascript
const { execSync } = require('child_process');

function convertVideo(inputPath, outputDir, slug, widget) {
  const mp4Path = path.join(outputDir, 'animated.mp4');
  const webpPath = path.join(outputDir, 'animated.webp');

  // MP4 — good quality, small file, universal support
  execSync(
    `ffmpeg -y -i "${inputPath}" -vf "fps=24,scale=${widget.viewport.width}:-2" ` +
    `-crf 26 -preset fast -movflags +faststart "${mp4Path}"`
  );

  // Animated WebP — optional, good for img tags without video element
  execSync(
    `ffmpeg -y -i "${inputPath}" -vf "fps=15,scale=${widget.viewport.width}:-2" ` +
    `-loop 0 -quality 75 "${webpPath}"`
  );
}
```

Trim video length using `-t {duration_in_seconds}` derived from `interaction_duration_ms` if the raw recording runs long.

---

### 6. Artisan Integration (Optional but Recommended)

Wrap the Node script in a Laravel Artisan command so it fits naturally into the project's tooling:

```
php artisan widgets:thumbnails --all
php artisan widgets:thumbnails --widget=event-calendar
php artisan widgets:thumbnails --static-only
```

The Artisan command simply validates the environment and delegates to the Node script via `Process::run()` or `exec()`, passing through CLI flags. This makes it discoverable to other developers on the project who may not know about the `scripts/` directory.

---

### 7. Suggested Directory Structure

```
scripts/
  generate-thumbnails.js        ← main capture script

resources/
  widget-thumbnails/
    manifest.json               ← widget manifest
  views/
    layouts/
      widget-demo.blade.php     ← minimal demo shell layout
    dev/
      widgets/
        event-calendar.blade.php
        donation-form.blade.php
        ... (one per widget)

routes/
  dev.php                       ← widget demo routes (non-production only)

app/
  Console/
    Commands/
      GenerateWidgetThumbnails.php   ← optional Artisan wrapper

public/
  img/
    widgets/
      thumbnails/
        event-calendar/
          static.png
          animated.mp4
          animated.webp
        donation-form/
          ...
```

---

## Implementation Notes and Edge Cases

- **CSS transitions:** Some widgets may have entrance animations. Either suppress them in demo mode (add a `no-transition` class when `?demo=1` is present) or add a fixed `waitForTimeout` before screenshotting to let them complete.
- **Fonts and assets:** Ensure the demo pages fully load web fonts before capture. A `waitForLoadState('networkidle')` is usually sufficient, but for locally-served font files it should be instant.
- **Responsive widgets:** If a widget behaves differently at tablet/mobile widths, consider adding optional `viewport_variants` to the manifest to capture those sizes as well. Name outputs `static-mobile.png`, etc.
- **Retina/HiDPI:** Playwright supports `deviceScaleFactor: 2` in the context options for 2x thumbnails. Recommended for static PNGs if they will be displayed at half size.
- **Git strategy:** Commit the manifest and scripts; consider gitignoring the generated thumbnail files and treating them as build artifacts (regenerated in CI or locally on demand), or commit them if you want them served directly without a build step.
- **Demo data quality:** The single highest-leverage investment in this system is the quality of the demo data seeded into each widget demo page. Blurry placeholder text, empty states, and "Lorem Ipsum" make poor thumbnails. Spend time here.

---

## Phased Rollout Recommendation

1. **Phase 1:** Build the demo layout and 3–5 representative demo routes. Write the Playwright script for static screenshots only. Validate the output path structure and file quality.
2. **Phase 2:** Add animated capture for those same 5 widgets. Prove the ffmpeg pipeline. Finalize the interaction library pattern.
3. **Phase 3:** Systematically add remaining widgets to the manifest. This should be low-effort at this point — mostly writing demo views and seeding data.
4. **Phase 4 (optional):** Wire the Artisan command. Add a CI step that regenerates thumbnails and flags diffs if widget views change.