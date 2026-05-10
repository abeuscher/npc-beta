import { ref } from 'vue'
import type { UpdateWidgetPayload } from '../types'

const DEBOUNCE_SAVE_MS = 350

export interface UseDebouncedSaveDeps {
  updateWidget: (widgetId: string, payload: UpdateWidgetPayload) => Promise<unknown>
  afterSave: (widgetId: string, payload: UpdateWidgetPayload) => void
}

export function useDebouncedSave(deps: UseDebouncedSaveDeps) {
  let debounceSaveTimer: ReturnType<typeof setTimeout> | null = null
  const pendingConfigChanges = ref<Record<string, UpdateWidgetPayload>>({})

  async function flushPendingSaves(): Promise<void> {
    if (debounceSaveTimer) {
      clearTimeout(debounceSaveTimer)
      debounceSaveTimer = null
    }
    const pending = { ...pendingConfigChanges.value }
    pendingConfigChanges.value = {}

    const savePromises = Object.entries(pending).map(([widgetId, payload]) =>
      deps.updateWidget(widgetId, payload).catch((e) =>
        console.error('Pending save failed:', e)
      )
    )
    if (savePromises.length > 0) {
      await Promise.all(savePromises)
    }
  }

  function flushDebouncedSave(widgetId: string, changes: UpdateWidgetPayload): void {
    const pending = pendingConfigChanges.value[widgetId] ?? {}
    pendingConfigChanges.value[widgetId] = { ...pending, ...changes }

    if (debounceSaveTimer) clearTimeout(debounceSaveTimer)
    debounceSaveTimer = setTimeout(() => {
      const toSave = { ...pendingConfigChanges.value }
      pendingConfigChanges.value = {}
      debounceSaveTimer = null

      for (const [id, payload] of Object.entries(toSave)) {
        deps.updateWidget(id, payload)
          .then(() => deps.afterSave(id, payload))
          .catch((e) => console.error('Debounced save failed:', e))
      }
    }, DEBOUNCE_SAVE_MS)
  }

  return { flushPendingSaves, flushDebouncedSave }
}
