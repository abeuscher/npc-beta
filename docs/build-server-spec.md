# Build Server Specification

A standalone service that accepts CSS, SCSS, and JS source files via HTTP and returns compiled, minified bundles. Used by the NonprofitCRM app (and potentially other instances) to compile public-facing assets without requiring Node.js on the application server for that purpose.

**Important:** The application retains Node/NPM for the admin panel build (Filament theme CSS via Vite). The build server handles only the public-facing bundle — widget styles, site SCSS, and widget JS.

---

## Scope

### What the build server does

- Accepts SCSS, CSS, and JS source as input
- Compiles SCSS to CSS
- Concatenates and minifies all CSS into a single output file
- Concatenates and minifies all JS into a single output file
- Returns the compiled bundles to the caller
- Caller specifies output filenames

### What the build server does NOT do

- Serve assets to end users (the app serves its own static files)
- Store state between requests (each request is self-contained)
- Handle Blade, PHP, or any server-side templating
- Build the admin panel CSS (that stays on Vite)
- Manage NPM dependencies for the caller (see Dependencies section)

---

## API

### `POST /build`

The single endpoint. Accepts a JSON payload describing the sources and desired output. Returns a JSON response containing the compiled bundles as base64-encoded strings (or binary download — see Open Questions).

#### Request

```json
{
    "output": {
        "css_filename": "public-abc123.css",
        "js_filename": "public-abc123.js"
    },
    "sources": {
        "scss": [
            {
                "path": "widgets/hero/style.scss",
                "content": "$hero-overlay: rgba(0,0,0,0.5);\n.widget--hero { ... }"
            },
            {
                "path": "widgets/carousel/style.scss",
                "content": ".widget-carousel { ... }"
            },
            {
                "path": "theme/public.scss",
                "content": "@tailwind base;\n@tailwind components;\n@tailwind utilities;\n..."
            }
        ],
        "css": [
            {
                "path": "vendor/swiper.css",
                "content": "/* swiper styles */"
            }
        ],
        "js": [
            {
                "path": "widgets/carousel/script.js",
                "content": "import Swiper from 'swiper'; ..."
            },
            {
                "path": "widgets/event-calendar/script.js",
                "content": "document.addEventListener(...);"
            }
        ]
    },
    "options": {
        "minify": true,
        "source_maps": false,
        "tailwind_config": {
            "content_paths": [],
            "theme": {
                "extend": {
                    "colors": {
                        "primary": "var(--color-primary, #0172ad)"
                    }
                }
            }
        }
    }
}
```

#### Field reference

| Field | Type | Required | Notes |
|---|---|---|---|
| output.css_filename | string | yes | Desired filename for the CSS bundle |
| output.js_filename | string | yes | Desired filename for the JS bundle |
| sources.scss | array | no | SCSS source files. Each has `path` (for error messages / source maps) and `content`. |
| sources.css | array | no | Raw CSS files. Concatenated after SCSS compilation. |
| sources.js | array | no | JS source files. |
| options.minify | boolean | no | Default true. Minify output. |
| options.source_maps | boolean | no | Default false. Include source maps. |
| options.tailwind_config | object | no | Tailwind configuration to use for this build. Allows per-instance theming. |

#### Response — success

```json
{
    "success": true,
    "files": {
        "css": {
            "filename": "public-abc123.css",
            "content": "<base64-encoded CSS>",
            "size_bytes": 57130
        },
        "js": {
            "filename": "public-abc123.js",
            "content": "<base64-encoded JS>",
            "size_bytes": 547480
        }
    },
    "build_time_ms": 3200
}
```

#### Response — error

```json
{
    "success": false,
    "errors": [
        {
            "type": "scss_compilation",
            "file": "widgets/hero/style.scss",
            "line": 14,
            "message": "Undefined variable: $hero-overlay"
        }
    ]
}
```

---

## Compilation pipeline

The build server processes sources in this order:

1. **SCSS compilation.** Each SCSS source is compiled individually (so errors report the correct file). The Tailwind config is applied if provided — the server runs Tailwind as a PostCSS plugin against the compiled output.
2. **CSS concatenation.** Compiled SCSS output + raw CSS sources are concatenated in the order provided.
3. **CSS minification.** If `minify: true`, the concatenated CSS is minified (cssnano or equivalent).
4. **JS concatenation.** JS sources are concatenated in the order provided.
5. **JS minification.** If `minify: true`, the concatenated JS is minified (terser or equivalent).
6. **Response.** Both bundles are returned.

### JS module resolution

The JS sources may contain `import` statements referencing NPM packages (e.g., `import Swiper from 'swiper'`). The build server needs to resolve these. See the Dependencies section.

---

## Dependencies (NPM packages used by widgets)

Some widgets depend on NPM packages (Swiper, Chart.js, jCalendar, etc.). The build server needs access to these at compile time for JS bundling.

### Approach: pre-installed common packages

The build server maintains a set of pre-installed NPM packages that are available to all builds. The server's own `package.json` includes these. When the caller sends JS that imports `swiper`, the build server's bundler resolves it from its own `node_modules/`.

The widget manifest's `dependencies.npm` field tells the app which packages a widget needs. The app can check against a known list of packages the build server supports. If a widget needs a package the server doesn't have, it's a manual addition to the build server — a deploy-time concern, not a per-request concern.

### Alternative: caller sends dependency source

The caller could include the full source of NPM dependencies in the request. This makes the server truly stateless but massively inflates request size. Not recommended for initial implementation.

### Package list (initial)

These are the NPM packages currently used by widgets:

| Package | Used by |
|---|---|
| swiper | carousel widget |
| jcalendar.js | event-calendar widget |
| tailwindcss | all (PostCSS plugin) |
| @tailwindcss/forms | all (Tailwind plugin) |
| @tailwindcss/typography | all (Tailwind plugin) |

---

## Authentication

The build server is not public. Requests must be authenticated.

### Approach: shared secret

Each app instance is issued an API key. The request includes it as a Bearer token:

```
Authorization: Bearer <api-key>
```

The build server validates the key before processing. Keys are stored in the build server's environment config. The app stores its key in `.env`:

```
BUILD_SERVER_URL=https://build.example.com
BUILD_SERVER_API_KEY=sk_build_xxxxxxxxxxxxx
```

### Rate limiting

Per-key rate limiting. Suggested: 10 builds per minute per key. Builds are expensive (CPU + memory for compilation), and a runaway loop in the app shouldn't take down the server.

---

## App-side integration

### Build service class

A Laravel service that collects widget sources and calls the build server:

```php
// App\Services\AssetBuildService

class AssetBuildService
{
    public function build(): BuildResult
    {
        // 1. Collect all installed widget SCSS, CSS, JS from widgets/ directory
        // 2. Collect site-level SCSS (public.scss, _custom.scss, theme variables)
        // 3. Build the request payload
        // 4. POST to build server
        // 5. Decode response
        // 6. Write CSS bundle to public/build/{css_filename}
        // 7. Write JS bundle to public/build/{js_filename}
        // 8. Update the manifest so the layout references the new filenames
        // 9. Return result with success/error info
    }
}
```

### Filename hashing

The app generates a content hash for cache-busting:

```php
$hash = substr(md5($allSourceContent), 0, 8);
$cssFilename = "public-widgets-{$hash}.css";
$jsFilename  = "public-widgets-{$hash}.js";
```

The manifest file (`public/build/manifest.json`) is updated with the new filenames. The layout reads from the manifest to generate `<link>` and `<script>` tags. This is the same pattern Vite already uses — the layout's asset loading doesn't need to change.

### Trigger points

The build is triggered by:

1. `php artisan widget:sync` — after syncing widget manifests, calls the build server
2. `php artisan build:public` — manual trigger for the public bundle
3. Theme editor save (post-beta) — when the user changes theme variables, triggers a rebuild

### Fallback

If the build server is unreachable, the app should:

1. Log the error
2. Continue serving the last known good bundle (the files in `public/build/` persist)
3. Surface a warning in the admin panel: "Asset build failed — public styles may be outdated"

The app should NOT attempt to compile assets locally as a fallback. If the build server is down, the existing bundle continues to work.

---

## Build server tech stack

### Recommended

| Component | Purpose |
|---|---|
| Node.js | Runtime — needed for Tailwind, PostCSS, terser, esbuild |
| Express or Fastify | HTTP server |
| Sass (dart-sass) | SCSS compilation |
| PostCSS + Tailwind | CSS processing |
| cssnano | CSS minification |
| esbuild | JS bundling and minification (fast, handles imports) |
| Redis (optional) | Build result caching by content hash |

### Why Node.js

The build server's job is running the same tools that Vite runs: Sass, PostCSS, Tailwind, esbuild. These are all Node.js tools. A Node.js server runs them natively without shelling out.

### Caching

If the build server receives a request with the same source content hash as a previous build, it can return the cached result immediately. This makes repeated `widget:sync` calls (with no changes) instant.

```
Cache key: sha256(JSON.stringify(sources) + JSON.stringify(options))
Cache TTL: 24 hours (or indefinite, invalidated by new build with same key)
```

---

## Deployment

The build server runs as a separate container or service. It does NOT run on the same machine as the app (that would defeat the purpose of offloading Node.js).

### Suggested deployment

- A small VPS or container (1-2 CPU, 2GB RAM is sufficient for compilation)
- Docker image with Node.js, pre-installed NPM packages, and the server code
- HTTPS with a valid certificate (the app sends source code over the wire)
- Environment variables: `API_KEYS`, `PORT`, `CACHE_DRIVER`

### Multi-instance support

The build server is stateless per-request. Multiple app instances can use the same build server. The API key identifies the instance for rate limiting. The Tailwind config in each request allows per-instance theming — instance A can have different colors than instance B.

---

## Open questions

1. **Response format — base64 JSON vs. binary download?** Base64 in JSON is simpler to parse but ~33% larger. A multipart response or separate download URLs would be more efficient for large bundles. For the initial implementation, base64 JSON is fine — the bundles are under 1MB.

2. **Tailwind content scanning.** Tailwind needs to scan HTML/Blade for class usage to tree-shake unused utilities. The caller would need to send template content (or extracted class lists) in the request for accurate purging. Alternatively, the build server can use `safelist` or skip purging and accept a larger CSS output. This needs design.

3. **Hot reload for local dev.** In local development, you want instant feedback when editing widget styles. The build server adds latency. Options: (a) keep a local Vite dev server for development, use the build server only for production builds; (b) the build server supports a websocket mode for watch/rebuild; (c) accept the latency — a 3-second round trip is fine for widget development.

4. **Source map support.** Source maps are useful for debugging but require the build server to return additional files. Worth implementing but can be deferred.

5. **Max payload size.** Need a limit on request body size to prevent abuse. 10MB should be generous for any reasonable set of widget sources.
