declare global {
  interface Window {
    __widgetLibs?: Record<string, { css?: string; js?: string }>
    Swiper?: any
    Chart?: any & { getChart?(el: HTMLCanvasElement): any }
    calendarJs?: any
    Alpine?: any
  }
}

const globalChecks: Record<string, () => boolean> = {
  swiper: () => !!window.Swiper,
  'chart.js': () => !!window.Chart,
  jcalendar: () => !!window.calendarJs,
}

export async function loadLibs(libs: string[]): Promise<void> {
  const manifest = window.__widgetLibs || {}
  const promises: Promise<void>[] = []

  for (const lib of libs) {
    const entry = manifest[lib]
    if (!entry) continue

    if (entry.css && !document.querySelector(`link[data-widget-lib='${lib}']`)) {
      promises.push(
        new Promise<void>((resolve) => {
          const link = document.createElement('link')
          link.rel = 'stylesheet'
          link.href = entry.css!
          link.dataset.widgetLib = lib
          link.onload = () => resolve()
          link.onerror = () => {
            console.warn('Failed to load widget lib CSS:', lib)
            resolve()
          }
          document.head.appendChild(link)
        })
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

export function reinitAlpine(scopeEl: HTMLElement): void {
  const Alpine = window.Alpine
  if (!Alpine) return

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
      console.warn('[useLibraryLoader] widget Alpine init error:', e.message)
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
