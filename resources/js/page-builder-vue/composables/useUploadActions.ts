import type { Ref } from 'vue'
import type { ApiClient } from '../api'
import type { Widget } from '../types'

export interface UseUploadActionsDeps {
  widgets: Ref<Record<string, Widget>>
  dirtyWidgets: Ref<Set<string>>
  saving: Ref<boolean>
  requireApi: () => ApiClient
  refreshPreview: (widgetId: string) => Promise<void>
}

export function useUploadActions(deps: UseUploadActionsDeps) {
  async function uploadImage(widgetId: string, key: string, file: File): Promise<string | null> {
    deps.saving.value = true
    try {
      const res = await deps.requireApi().uploadImage(widgetId, key, file)
      if (deps.widgets.value[widgetId]) {
        const w = deps.widgets.value[widgetId]
        w.config = { ...w.config, [key]: res.media_id }
        w.image_urls = { ...w.image_urls, [key]: res.url }
      }
      deps.dirtyWidgets.value.add(widgetId)
      deps.refreshPreview(widgetId)
      return res.url
    } finally {
      deps.saving.value = false
    }
  }

  async function removeImage(widgetId: string, key: string): Promise<void> {
    deps.saving.value = true
    try {
      await deps.requireApi().removeImage(widgetId, key)
      if (deps.widgets.value[widgetId]) {
        const w = deps.widgets.value[widgetId]
        w.config = { ...w.config, [key]: null }
        w.image_urls = { ...w.image_urls, [key]: null }
      }
      deps.dirtyWidgets.value.add(widgetId)
      deps.refreshPreview(widgetId)
    } finally {
      deps.saving.value = false
    }
  }

  async function uploadAppearanceImage(widgetId: string, file: File): Promise<string | null> {
    deps.saving.value = true
    try {
      const res = await deps.requireApi().uploadAppearanceImage(widgetId, file)
      if (deps.widgets.value[widgetId]) {
        deps.widgets.value[widgetId].appearance_image_url = res.url
      }
      deps.dirtyWidgets.value.add(widgetId)
      deps.refreshPreview(widgetId)
      return res.url
    } finally {
      deps.saving.value = false
    }
  }

  async function removeAppearanceImage(widgetId: string): Promise<void> {
    deps.saving.value = true
    try {
      await deps.requireApi().removeAppearanceImage(widgetId)
      if (deps.widgets.value[widgetId]) {
        deps.widgets.value[widgetId].appearance_image_url = null
      }
      deps.dirtyWidgets.value.add(widgetId)
      deps.refreshPreview(widgetId)
    } finally {
      deps.saving.value = false
    }
  }

  return {
    uploadImage,
    removeImage,
    uploadAppearanceImage,
    removeAppearanceImage,
  }
}
