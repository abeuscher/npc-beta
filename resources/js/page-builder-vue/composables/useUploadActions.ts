import type { Ref } from 'vue'
import type { ApiClient } from '../api'
import type { DedupDecision, DedupMatch, MediaBrowserTarget, Widget } from '../types'
import { sha256Hex } from '../utils/hashFile'

export interface UseUploadActionsDeps {
  widgets: Ref<Record<string, Widget>>
  dirtyWidgets: Ref<Set<string>>
  saving: Ref<boolean>
  requireApi: () => ApiClient
  refreshPreview: (widgetId: string) => Promise<void>
  requestDedupDecision: (matches: DedupMatch[]) => Promise<DedupDecision>
}

export function useUploadActions(deps: UseUploadActionsDeps) {
  // Before storing an upload, hash the bytes and ask the library whether it
  // already holds this asset. A match opens the warn-and-offer prompt; the
  // operator's choice flows back here. Hashing or the check failing degrades
  // silently to a plain upload — dedup is a nudge, never a gate.
  async function dedupGate(file: File): Promise<DedupDecision> {
    const hash = await sha256Hex(file)
    if (!hash) return { type: 'keep-new' }

    let matches: DedupMatch[]
    try {
      matches = (await deps.requireApi().dedupCheck(hash, file.name)).matches
    } catch {
      return { type: 'keep-new' }
    }

    if (matches.length === 0) return { type: 'keep-new' }

    return deps.requestDedupDecision(matches)
  }

  async function uploadImage(widgetId: string, key: string, file: File): Promise<string | null> {
    const decision = await dedupGate(file)
    if (decision.type === 'cancel') return null

    deps.saving.value = true
    try {
      const res =
        decision.type === 'use-existing'
          ? await deps.requireApi().useExistingImage(widgetId, key, decision.mediaId)
          : await deps.requireApi().uploadImage(widgetId, key, file)
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
    const decision = await dedupGate(file)
    if (decision.type === 'cancel') return null

    deps.saving.value = true
    try {
      const res =
        decision.type === 'use-existing'
          ? await deps.requireApi().useExistingAppearanceImage(widgetId, decision.mediaId)
          : await deps.requireApi().uploadAppearanceImage(widgetId, file)
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

  // Attach an already-stored library image (chosen in the media browser) to a
  // widget field or appearance background. The use-existing endpoints introduce
  // no new file (CAS copy), so no dedup gate — this is the browse-and-pick path.
  // Mirrors the use-existing branches of uploadImage / uploadAppearanceImage
  // without touching them, so the existing upload/remove behaviour is untouched.
  async function useExistingMedia(target: MediaBrowserTarget, mediaId: number): Promise<string | null> {
    deps.saving.value = true
    try {
      const w = deps.widgets.value[target.widgetId]
      if (target.kind === 'appearance') {
        const res = await deps.requireApi().useExistingAppearanceImage(target.widgetId, mediaId)
        if (w) w.appearance_image_url = res.url
        deps.dirtyWidgets.value.add(target.widgetId)
        deps.refreshPreview(target.widgetId)
        return res.url
      }

      const res = await deps.requireApi().useExistingImage(target.widgetId, target.key, mediaId)
      if (w) {
        w.config = { ...w.config, [target.key]: res.media_id }
        w.image_urls = { ...w.image_urls, [target.key]: res.url }
      }
      deps.dirtyWidgets.value.add(target.widgetId)
      deps.refreshPreview(target.widgetId)
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
    useExistingMedia,
  }
}
