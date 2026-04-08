import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type {
  Widget,
  WidgetType,
  Collection,
  Tag,
  PageRef,
  EventRef,
  BootstrapData,
  CreateWidgetPayload,
  UpdateWidgetPayload,
  ReorderItem,
} from '../types'
import * as api from '../api'

export const useEditorStore = defineStore('editor', () => {
  // Core widget data
  const pageId = ref('')
  const pageType = ref('default')
  const widgets = ref<Record<string, Widget>>({})
  const rootOrder = ref<string[]>([])
  const selectedBlockId = ref<string | null>(null)
  const editorMode = ref<'edit' | 'handles'>('edit')

  // Preview state
  const dirtyWidgets = ref<Set<string>>(new Set())
  const requiredLibs = ref<string[]>([])

  // Widget type registry
  const widgetTypes = ref<WidgetType[]>([])
  const requiredHandles = ref<string[]>([])

  // Lookup data
  const collections = ref<Collection[]>([])
  const tags = ref<Tag[]>([])
  const pages = ref<PageRef[]>([])
  const events = ref<EventRef[]>([])

  // Inline image upload URL (from bootstrap data, used by RichTextField)
  const inlineImageUploadUrl = ref('')

  // Color swatches (shared across all color picker fields)
  const colorSwatches = ref<string[]>([])

  // UI state
  const saving = ref(false)

  // Debounced save state
  let debounceSaveTimer: ReturnType<typeof setTimeout> | null = null
  const pendingConfigChanges = ref<Record<string, Record<string, any>>>({})

  // ── Getters ────────────────────────────────────────────────────────────

  const rootWidgets = computed(() =>
    rootOrder.value.map((id) => widgets.value[id]).filter(Boolean)
  )

  const selectedWidget = computed(() =>
    selectedBlockId.value ? widgets.value[selectedBlockId.value] ?? null : null
  )

  function childrenOf(parentId: string): Record<number, Widget[]> {
    const parent = widgets.value[parentId]
    return parent?.children ?? {}
  }

  function isWidgetDirty(id: string): boolean {
    return dirtyWidgets.value.has(id)
  }

  const columnTargets = computed(() =>
    rootWidgets.value.filter((w) => w.widget_type_handle === 'column_widget')
  )

  // ── Actions ────────────────────────────────────────────────────────────

  function loadTree(data: BootstrapData): void {
    pageId.value = data.page_id
    pageType.value = data.page_type
    widgetTypes.value = data.widget_types
    requiredHandles.value = data.required_handles
    collections.value = data.collections
    tags.value = data.tags
    pages.value = data.pages
    events.value = data.events
    inlineImageUploadUrl.value = data.inline_image_upload_url ?? ''
    colorSwatches.value = data.color_swatches ?? []

    populateWidgets(data.widgets)
    requiredLibs.value = data.required_libs
  }

  function populateWidgets(tree: Widget[]): void {
    const flat: Record<string, Widget> = {}
    const order: string[] = []

    for (const w of tree) {
      flat[w.id] = w
      order.push(w.id)
    }

    widgets.value = flat
    rootOrder.value = order
    dirtyWidgets.value = new Set()
  }

  function selectBlock(id: string | null): void {
    selectedBlockId.value = id
  }

  function setMode(mode: 'edit' | 'handles'): void {
    editorMode.value = mode
  }

  async function createWidget(payload: CreateWidgetPayload): Promise<Widget | null> {
    saving.value = true
    try {
      const res = await api.createWidget(pageId.value, payload)
      populateWidgets(res.tree)
      requiredLibs.value = res.required_libs
      return res.widget
    } finally {
      saving.value = false
    }
  }

  async function updateWidget(id: string, changes: UpdateWidgetPayload): Promise<Widget | null> {
    saving.value = true
    try {
      const res = await api.updateWidget(id, changes)
      const updated = res.widget
      if (widgets.value[id]) {
        widgets.value[id] = { ...widgets.value[id], ...updated }
      }
      dirtyWidgets.value.add(id)
      return updated
    } finally {
      saving.value = false
    }
  }

  async function deleteWidget(id: string): Promise<void> {
    saving.value = true
    try {
      const res = await api.deleteWidget(id)
      populateWidgets(res.tree)
      requiredLibs.value = res.required_libs
      if (selectedBlockId.value === id) {
        selectedBlockId.value = null
      }
    } finally {
      saving.value = false
    }
  }

  async function copyWidget(id: string): Promise<Widget | null> {
    saving.value = true
    try {
      const res = await api.copyWidget(id)
      populateWidgets(res.tree)
      requiredLibs.value = res.required_libs
      return res.widget
    } finally {
      saving.value = false
    }
  }

  async function reorderWidgets(items: ReorderItem[]): Promise<void> {
    saving.value = true
    try {
      const res = await api.reorderWidgets(pageId.value, items)
      populateWidgets(res.tree)
      requiredLibs.value = res.required_libs
    } finally {
      saving.value = false
    }
  }

  function replaceTree(data: { widgets: Widget[]; required_libs: string[] }): void {
    populateWidgets(data.widgets)
    requiredLibs.value = data.required_libs
  }

  async function reloadTree(): Promise<void> {
    try {
      const res = await api.getWidgets(pageId.value)
      replaceTree(res)
    } catch (e) {
      console.error('Failed to reload widget tree:', e)
    }
  }

  async function refreshPreview(id: string): Promise<void> {
    // Flush any pending debounced saves so the server has the latest config
    if (debounceSaveTimer) {
      clearTimeout(debounceSaveTimer)
      debounceSaveTimer = null
    }
    const pending = { ...pendingConfigChanges.value }
    pendingConfigChanges.value = {}

    // Wait for all pending saves to complete before fetching preview
    const savePromises = Object.entries(pending).map(([widgetId, payload]) =>
      updateWidget(widgetId, payload).catch((e) =>
        console.error('Pre-preview save failed:', e)
      )
    )
    if (savePromises.length > 0) {
      await Promise.all(savePromises)
    }

    try {
      const res = await api.getPreview(id)
      if (widgets.value[id]) {
        widgets.value[id] = { ...widgets.value[id], preview_html: res.html }
      }
      dirtyWidgets.value.delete(id)
    } catch (e) {
      console.error('Preview refresh failed:', e)
    }
  }

  /**
   * Update a widget's config locally (instant UI update) and queue a debounced API save.
   * If `label` is provided (and key is null), saves a label change instead.
   */
  function updateLocalConfig(
    widgetId: string,
    key: string | null,
    value?: any,
    label?: string
  ): void {
    const w = widgets.value[widgetId]
    if (!w) return

    if (label !== undefined && key === null) {
      // Label change
      w.label = label
      flushDebouncedSave(widgetId, { label })
      return
    }

    if (key !== null) {
      w.config = { ...w.config, [key]: value }
      dirtyWidgets.value.add(widgetId)
      flushDebouncedSave(widgetId, { config: { ...w.config } })
    }
  }

  function flushDebouncedSave(widgetId: string, changes: UpdateWidgetPayload): void {
    // Merge pending changes for this widget
    const pending = pendingConfigChanges.value[widgetId] ?? {}
    pendingConfigChanges.value[widgetId] = { ...pending, ...changes }

    if (debounceSaveTimer) clearTimeout(debounceSaveTimer)
    debounceSaveTimer = setTimeout(() => {
      const toSave = { ...pendingConfigChanges.value }
      pendingConfigChanges.value = {}
      debounceSaveTimer = null

      for (const [id, payload] of Object.entries(toSave)) {
        updateWidget(id, payload).catch((e) =>
          console.error('Debounced save failed:', e)
        )
      }
    }, 500)
  }

  async function uploadImage(widgetId: string, key: string, file: File): Promise<string | null> {
    saving.value = true
    try {
      const res = await api.uploadImage(widgetId, key, file)
      if (widgets.value[widgetId]) {
        const w = widgets.value[widgetId]
        w.config = { ...w.config, [key]: res.media_id }
        w.image_urls = { ...w.image_urls, [key]: res.url }
      }
      dirtyWidgets.value.add(widgetId)
      return res.url
    } finally {
      saving.value = false
    }
  }

  async function removeImage(widgetId: string, key: string): Promise<void> {
    saving.value = true
    try {
      await api.removeImage(widgetId, key)
      if (widgets.value[widgetId]) {
        const w = widgets.value[widgetId]
        w.config = { ...w.config, [key]: null }
        w.image_urls = { ...w.image_urls, [key]: null }
      }
      dirtyWidgets.value.add(widgetId)
    } finally {
      saving.value = false
    }
  }

  function updateLocalStyleConfig(widgetId: string, key: string, value: any): void {
    const w = widgets.value[widgetId]
    if (!w) return

    w.style_config = { ...w.style_config, [key]: value }
    dirtyWidgets.value.add(widgetId)
    flushDebouncedSave(widgetId, { style_config: { ...w.style_config } })
  }

  function updateLocalQueryConfig(
    widgetId: string,
    collHandle: string,
    key: string,
    value: any
  ): void {
    const w = widgets.value[widgetId]
    if (!w) return

    const collConfig = { ...(w.query_config[collHandle] ?? {}), [key]: value }
    w.query_config = { ...w.query_config, [collHandle]: collConfig }
    dirtyWidgets.value.add(widgetId)
    flushDebouncedSave(widgetId, { query_config: { ...w.query_config } })
  }

  async function saveColorSwatchesAction(swatches: string[]): Promise<void> {
    colorSwatches.value = swatches
    try {
      const res = await api.saveColorSwatches(swatches)
      colorSwatches.value = res.swatches
    } catch (e) {
      console.error('Failed to save color swatches:', e)
    }
  }

  return {
    // State
    pageId,
    pageType,
    widgets,
    rootOrder,
    selectedBlockId,
    editorMode,
    dirtyWidgets,
    requiredLibs,
    widgetTypes,
    requiredHandles,
    collections,
    tags,
    pages,
    events,
    saving,
    inlineImageUploadUrl,
    colorSwatches,

    // Getters
    rootWidgets,
    selectedWidget,
    childrenOf,
    isWidgetDirty,
    columnTargets,

    // Actions
    loadTree,
    replaceTree,
    reloadTree,
    selectBlock,
    setMode,
    createWidget,
    updateWidget,
    updateLocalConfig,
    deleteWidget,
    copyWidget,
    reorderWidgets,
    refreshPreview,
    uploadImage,
    removeImage,
    updateLocalStyleConfig,
    updateLocalQueryConfig,
    saveColorSwatches: saveColorSwatchesAction,
  }
})
