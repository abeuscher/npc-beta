// Shared widget-owned hydration (session 355).
//
// One routine both surfaces call so the editor's widget hydration can no longer
// drift from the public site's. The widget HTML/CSS and the per-widget init
// logic are already single-sourced (one `WidgetRenderer`; each widget builds its
// own Swiper/Chart inside its Alpine `x-data` init()). What diverged was the
// *lifecycle* that runs those inits:
//
//   • Public  — `public.js` bundles the libs, assigns the window globals, then
//     calls `Alpine.start()` once over `document`. Alpine walks the tree and
//     runs every widget's init() for free. → hydrate(document, { firstInit: true }).
//   • Editor  — Filament owns/starts Alpine; after each `v-html` canvas
//     injection Alpine will not auto-init the new subtree, so we reproduce what
//     `Alpine.start()` gives public — but scoped to the subtree and repeatable
//     (destroy the prior Swiper/Chart, lift/restore x-ignore, re-run initTree,
//     then swiper.update()). → hydrate(scopeEl, { libs }).
//
// This module imports none of the libs — it operates over the `window.*` globals
// exactly as the editor always has, so neither build graph duplicates
// Alpine/Swiper/Chart into a second bundle.

declare global {
  interface Window {
    __widgetLibs?: Record<string, { css?: string; js?: string }>
    Swiper?: any
    Chart?: any & { getChart?(el: HTMLCanvasElement): any }
    Alpine?: any
  }
}

const globalChecks: Record<string, () => boolean> = {
  swiper: () => !!window.Swiper,
  'chart.js': () => !!window.Chart,
}

/**
 * Lazy lib acquisition for surfaces that do not bundle the libs (the editor).
 * Resolves each handle against the `window.__widgetLibs` manifest and injects
 * any missing CSS/JS. Idempotent — already-present libs are skipped — so it is
 * safe to call before every re-init. Public omits this (libs are bundled).
 */
export async function loadLibs(libs: string[]): Promise<void> {
  const manifest = window.__widgetLibs || {}
  const promises: Promise<void>[] = []

  for (const lib of libs) {
    const entry = manifest[lib]
    if (!entry) continue

    if (entry.css && !document.querySelector(`style[data-widget-lib='${lib}']`)) {
      promises.push(
        (async () => {
          // Mirror the public bundle: vendor CSS lives in `@layer reset` so widget
          // styles (`@layer widgets`) win by layer order, not specificity (session
          // 332). Injected as a plain <link> the vendor CSS would be unlayered and
          // beat every layer — which made the editor render Swiper's default pager
          // instead of the designed one. Fetch + wrap so it lands in the right layer.
          try {
            const res = await fetch(entry.css!)
            const css = await res.text()
            const style = document.createElement('style')
            style.dataset.widgetLib = lib
            style.textContent = `@layer reset {\n${css}\n}`
            document.head.appendChild(style)
          } catch {
            console.warn('Failed to load widget lib CSS:', lib)
          }
        })()
      )
    }

    const check = globalChecks[lib]
    const alreadyLoaded = check
      ? check()
      : !!document.querySelector(`script[data-widget-lib='${lib}']`)

    if (!alreadyLoaded && entry.js) {
      promises.push(
        new Promise<void>((resolve) => {
          const script = document.createElement('script')
          script.src = entry.js!
          script.dataset.widgetLib = lib
          script.onload = () => resolve()
          script.onerror = () => {
            console.warn('Failed to load widget lib JS:', lib)
            resolve()
          }
          document.head.appendChild(script)
        })
      )
    }
  }

  await Promise.all(promises)
}

/**
 * Re-init the widget subtree in place: destroy any prior Swiper/Chart instances,
 * lift x-ignore so Alpine will re-walk the tree, destroy + re-init each Alpine
 * tree, restore x-ignore, then update Swiper after layout settles. Safe on a
 * never-initialized subtree (the destroy passes find nothing), so it doubles as
 * first-init for a dynamically-injected tree.
 */
function reinitSubtree(scopeEl: HTMLElement, Alpine: any): void {
  // Destroy existing Swiper instances
  scopeEl.querySelectorAll('.swiper').forEach((el: any) => {
    if (el.swiper) el.swiper.destroy(true, true)
  })

  // Destroy existing Chart.js instances
  scopeEl.querySelectorAll('canvas').forEach((el) => {
    const chartInstance = window.Chart?.getChart?.(el as HTMLCanvasElement)
    if (chartInstance) chartInstance.destroy()
  })

  // Temporarily lift x-ignore to allow Alpine re-init
  const ignoreEls = scopeEl.querySelectorAll('[x-ignore]')
  ignoreEls.forEach((el: any) => {
    el.removeAttribute('x-ignore')
    delete el._x_ignore
    delete el._x_ignoreSelf
    el.setAttribute('data-x-ignore-lifted', '')
  })

  // Destroy and re-init Alpine trees
  scopeEl.querySelectorAll('[x-data]').forEach((el: any) => {
    if (el._x_dataStack) {
      Alpine.destroyTree(el)
    }
    try {
      Alpine.initTree(el)
    } catch (e: any) {
      console.warn('[hydrate] widget Alpine init error:', e.message)
    }
  })

  // Restore x-ignore attributes
  scopeEl.querySelectorAll('[data-x-ignore-lifted]').forEach((el: any) => {
    el.removeAttribute('data-x-ignore-lifted')
    el.setAttribute('x-ignore', '')
    el._x_ignore = true
  })

  // Update Swiper instances after Alpine init
  requestAnimationFrame(() => {
    scopeEl.querySelectorAll('.swiper').forEach((el: any) => {
      if (el.swiper) el.swiper.update()
    })
  })
}

export interface HydrateOptions {
  /**
   * Whole-document first init. Public passes this once: register-side setup
   * (custom components, stores) has already run on `window.Alpine`, so all that
   * remains is `Alpine.start()`, which walks the document and runs every
   * widget's init(). Omitted by the editor, whose Alpine is already started.
   */
  firstInit?: boolean
  /**
   * Lib handles to ensure present before init (the editor's manifest-lazy
   * acquisition). Omitted by public, which bundles the libs.
   */
  libs?: string[]
}

/**
 * Hydrate a widget root: ensure its libs are present + correctly layered, then
 * run the Alpine init lifecycle over it. Public calls it once over `document`;
 * the editor calls it over each freshly-injected canvas subtree.
 */
export async function hydrate(
  rootEl: Document | HTMLElement,
  opts: HydrateOptions = {},
): Promise<void> {
  const Alpine = window.Alpine
  if (!Alpine) return

  if (opts.libs && opts.libs.length) {
    await loadLibs(opts.libs)
  }

  if (opts.firstInit) {
    // First-init: Alpine.start() walks the document and runs each widget's
    // init() (which constructs its own Swiper/Chart from the window globals).
    Alpine.start()
    return
  }

  reinitSubtree(rootEl as HTMLElement, Alpine)
}
