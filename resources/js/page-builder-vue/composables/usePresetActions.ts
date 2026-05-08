import type { Ref } from 'vue'
import type { ApiClient } from '../api'
import type { Widget, WidgetType, WidgetPreset, UpdateWidgetPayload } from '../types'

export interface UsePresetActionsDeps {
  widgets: Ref<Record<string, Widget>>
  dirtyWidgets: Ref<Set<string>>
  widgetTypes: Ref<WidgetType[]>
  requireApi: () => ApiClient
  flushPendingSaves: () => Promise<void>
  flushDebouncedSave: (widgetId: string, changes: UpdateWidgetPayload) => void
}

export function usePresetActions(deps: UsePresetActionsDeps) {
  async function saveDraftPreset(widgetId: string): Promise<void> {
    const w = deps.widgets.value[widgetId]
    if (!w) return

    await deps.flushPendingSaves()

    const res = await deps.requireApi().createDraftPreset(w.widget_type_id, widgetId)
    const wt = deps.widgetTypes.value.find((t) => t.id === w.widget_type_id)
    if (wt) {
      const next = [...(wt.draft_presets ?? []), res.preset]
      wt.draft_presets = next
    }
  }

  async function renameDraftPreset(
    presetId: string,
    payload: { label?: string; description?: string | null; handle?: string }
  ): Promise<void> {
    const res = await deps.requireApi().updateDraftPreset(presetId, payload)
    for (const wt of deps.widgetTypes.value) {
      const drafts = wt.draft_presets ?? []
      const idx = drafts.findIndex((d) => d.id === presetId)
      if (idx !== -1) {
        const next = drafts.slice()
        next[idx] = res.preset
        wt.draft_presets = next
        break
      }
    }
  }

  async function deleteDraftPreset(presetId: string): Promise<void> {
    await deps.requireApi().deleteDraftPreset(presetId)
    for (const wt of deps.widgetTypes.value) {
      const drafts = wt.draft_presets ?? []
      const idx = drafts.findIndex((d) => d.id === presetId)
      if (idx !== -1) {
        wt.draft_presets = drafts.filter((d) => d.id !== presetId)
        break
      }
    }
  }

  function applyPreset(widgetId: string, preset: WidgetPreset): void {
    const w = deps.widgets.value[widgetId]
    if (!w) return

    const nextConfig = { ...(w.config ?? {}), ...preset.config }
    const nextAppearance = { ...preset.appearance_config } as any

    w.config = nextConfig
    w.appearance_config = nextAppearance
    deps.dirtyWidgets.value.add(widgetId)
    deps.flushDebouncedSave(widgetId, {
      config: nextConfig,
      appearance_config: nextAppearance,
    })
  }

  return {
    saveDraftPreset,
    renameDraftPreset,
    deleteDraftPreset,
    applyPreset,
  }
}
