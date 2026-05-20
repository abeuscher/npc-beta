import { ref, type Ref } from 'vue'
import { ApiError, type ApiClient } from '../api'
import type { Widget } from '../types'

const BLUR_DELAY_MS = 250
const SPINNER_DELAY_MS = 500

export interface UseRefreshPreviewDeps {
  widgets: Ref<Record<string, Widget>>
  dirtyWidgets: Ref<Set<string>>
  requireApi: () => ApiClient
  flushPendingSaves: () => Promise<void>
  // Session 305 §6.5: centralised inline-edit refresh suppression. While
  // this is set to a widget id, refreshPreview() short-circuits for that
  // widget — every refresh path (save echo, per-region needsConfig
  // watcher, future programmatic calls) automatically honours the
  // suppression. The legitimate reconciling refresh after teardown is
  // unaffected because endInlineEdit() clears this ref before calling
  // refreshPreview().
  inlineActiveWidgetId: Ref<string | null>
}

export function useRefreshPreview(deps: UseRefreshPreviewDeps) {
  const refreshCounts = ref<Record<string, number>>({})
  const abortControllers = new Map<string, AbortController>()
  const blurTimers = new Map<string, ReturnType<typeof setTimeout>>()
  const spinnerTimers = new Map<string, ReturnType<typeof setTimeout>>()
  const indicatorStage = ref<Record<string, 0 | 1 | 2>>({})
  const previewErrors = ref<Record<string, string | null>>({})

  function clearIndicatorTimers(id: string): void {
    const bt = blurTimers.get(id)
    if (bt) {
      clearTimeout(bt)
      blurTimers.delete(id)
    }
    const st = spinnerTimers.get(id)
    if (st) {
      clearTimeout(st)
      spinnerTimers.delete(id)
    }
  }

  function incrementRefreshCount(id: string): void {
    const next = (refreshCounts.value[id] ?? 0) + 1
    refreshCounts.value[id] = next
    if (next === 1) {
      // 0 → 1 transition: arm the cascading indicator timers
      indicatorStage.value[id] = 0
      blurTimers.set(
        id,
        setTimeout(() => {
          if ((refreshCounts.value[id] ?? 0) > 0) {
            indicatorStage.value[id] = 1
          }
        }, BLUR_DELAY_MS)
      )
      spinnerTimers.set(
        id,
        setTimeout(() => {
          if ((refreshCounts.value[id] ?? 0) > 0) {
            indicatorStage.value[id] = 2
          }
        }, SPINNER_DELAY_MS)
      )
    }
  }

  function decrementRefreshCount(id: string): void {
    const next = (refreshCounts.value[id] ?? 1) - 1
    if (next <= 0) {
      refreshCounts.value[id] = 0
      clearIndicatorTimers(id)
      indicatorStage.value[id] = 0
    } else {
      refreshCounts.value[id] = next
    }
  }

  async function refreshPreview(id: string): Promise<void> {
    // Session 305 §6.5 — A13 enforcement: never swap the preview HTML of
    // the widget whose editor is currently mounted. Centralised here so
    // every refresh path (echo, watchers, future callers) honours it.
    if (id === deps.inlineActiveWidgetId.value) return

    await deps.flushPendingSaves()

    // Abort any in-flight refresh for the same widget so a stale render
    // can't overwrite a newer one.
    const existing = abortControllers.get(id)
    if (existing) {
      existing.abort()
      abortControllers.delete(id)
    }

    const controller = new AbortController()
    abortControllers.set(id, controller)
    incrementRefreshCount(id)

    try {
      const res = await deps.requireApi().getPreview(id, controller.signal)
      if (deps.widgets.value[id]) {
        deps.widgets.value[id].preview_html = res.html
      }
      deps.dirtyWidgets.value.delete(id)
      previewErrors.value[id] = null
    } catch (e: any) {
      if (e?.name === 'AbortError') {
        // Superseded by a newer refresh — silent.
        return
      }
      const message =
        e instanceof ApiError
          ? e.message
          : e?.message
            ? e.message
            : 'Network error'
      previewErrors.value[id] = message
      console.error('Preview refresh failed:', e)
    } finally {
      if (abortControllers.get(id) === controller) {
        abortControllers.delete(id)
      }
      decrementRefreshCount(id)
    }
  }

  function widgetRefreshing(id: string): boolean {
    return (refreshCounts.value[id] ?? 0) > 0
  }

  function widgetIndicatorStage(id: string): 0 | 1 | 2 {
    return indicatorStage.value[id] ?? 0
  }

  function widgetPreviewError(id: string): string | null {
    return previewErrors.value[id] ?? null
  }

  return {
    refreshPreview,
    widgetRefreshing,
    widgetIndicatorStage,
    widgetPreviewError,
  }
}
