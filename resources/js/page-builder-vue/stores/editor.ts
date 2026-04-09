import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type {
  Widget,
  PageLayout,
  PageItem,
  WidgetType,
  Collection,
  Tag,
  PageRef,
  EventRef,
  BootstrapData,
  CreateWidgetPayload,
  UpdateWidgetPayload,
  CreateLayoutPayload,
  UpdateLayoutPayload,
  ReorderItem,
} from '../types'
import * as api from '../api'

export const useEditorStore = defineStore('editor', () => {
  // Core data
  const pageId = ref('')
  const pageType = ref('default')
  const widgets = ref<Record<string, Widget>>({}) // flat map of all widgets (root + inside layouts)
  const layouts = ref<Record<string, PageLayout>>({}) // flat map of layouts
  const pageItems = ref<PageItem[]>([]) // ordered merged page flow
  const rootOrder = ref<string[]>([]) // root widget IDs only — kept for backward compat with PreviewCanvas drag-end
  const selectedItemId = ref<string | null>(null)
  const selectedItemType = ref<'widget' | 'layout' | null>(null)
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

  // Debounced layout save state
  let debounceLayoutTimer: ReturnType<typeof setTimeout> | null = null
  const pendingLayoutChanges = ref<Record<string, UpdateLayoutPayload>>({})

  // ── Getters ────────────────────────────────────────────────────────────

  const rootWidgets = computed(() =>
    rootOrder.value.map((id) => widgets.value[id]).filter(Boolean)
  )

  const selectedWidget = computed(() => {
    if (selectedItemType.value !== 'widget' || !selectedItemId.value) return null
    return widgets.value[selectedItemId.value] ?? null
  })

  const selectedLayout = computed(() => {
    if (selectedItemType.value !== 'layout' || !selectedItemId.value) return null
    return layouts.value[selectedItemId.value] ?? null
  })

  // Backward-compat alias for components that still read selectedBlockId
  const selectedBlockId = computed({
    get: () => (selectedItemType.value === 'widget' ? selectedItemId.value : null),
    set: (id: string | null) => {
      if (id === null) {
        selectedItemId.value = null
        selectedItemType.value = null
      } else {
        selectedItemId.value = id
        selectedItemType.value = 'widget'
      }
    },
  })

  function childrenOf(_parentId: string): Record<number, Widget[]> {
    // Legacy helper — widgets no longer have children. Layouts hold widgets in slots.
    return {}
  }

  function isWidgetDirty(id: string): boolean {
    return dirtyWidgets.value.has(id)
  }

  // No more column widgets — kept for backward compat with any stale references.
  const columnTargets = computed(() => [] as Widget[])

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

    populateFromItems(data.items ?? [])
    requiredLibs.value = data.required_libs
  }

  function populateFromItems(items: PageItem[]): void {
    const widgetMap: Record<string, Widget> = {}
    const layoutMap: Record<string, PageLayout> = {}
    const rootIds: string[] = []
    const orderedItems: PageItem[] = []

    for (const item of items) {
      if (item.type === 'widget') {
        const w = item as Widget & { type: 'widget' }
        widgetMap[w.id] = w
        rootIds.push(w.id)
        orderedItems.push(w as PageItem)
      } else {
        const layout = item as PageLayout & { type: 'layout' }

        // Normalize slots: ensure every column index 0..columns-1 has a real array.
        // This guarantees vuedraggable always has a stable mutable reference per slot,
        // even for empty columns.
        const rawSlots = (layout.slots ?? {}) as Record<string | number, Widget[]>
        const normalized: Record<number, Widget[]> = {}
        for (let i = 0; i < layout.columns; i++) {
          const slot = rawSlots[i] ?? rawSlots[String(i)] ?? []
          normalized[i] = slot
          for (const sw of slot) {
            widgetMap[sw.id] = sw
          }
        }
        layout.slots = normalized as any

        // Use the SAME object reference in both maps so mutations propagate.
        layoutMap[layout.id] = layout
        orderedItems.push(layout as PageItem)
      }
    }

    widgets.value = widgetMap
    layouts.value = layoutMap
    pageItems.value = orderedItems
    rootOrder.value = rootIds
    dirtyWidgets.value = new Set()
  }

  function selectItem(id: string | null, type: 'widget' | 'layout' | null = null): void {
    if (id === null) {
      selectedItemId.value = null
      selectedItemType.value = null
      return
    }
    selectedItemId.value = id
    selectedItemType.value = type ?? 'widget'
  }

  // Backward-compat: select a widget by ID
  function selectBlock(id: string | null): void {
    selectItem(id, id === null ? null : 'widget')
  }

  function setMode(mode: 'edit' | 'handles'): void {
    editorMode.value = mode
  }

  // ── Widget CRUD ────────────────────────────────────────────────────────

  async function createWidget(payload: CreateWidgetPayload): Promise<Widget | null> {
    saving.value = true
    try {
      const res = await api.createWidget(pageId.value, payload)
      populateFromItems(res.items)
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
      populateFromItems(res.items)
      requiredLibs.value = res.required_libs
      if (selectedItemId.value === id) {
        selectedItemId.value = null
        selectedItemType.value = null
      }
    } finally {
      saving.value = false
    }
  }

  async function copyWidget(id: string): Promise<Widget | null> {
    saving.value = true
    try {
      const res = await api.copyWidget(id)
      populateFromItems(res.items)
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
      populateFromItems(res.items)
      requiredLibs.value = res.required_libs
    } finally {
      saving.value = false
    }
  }

  // ── Layout CRUD ────────────────────────────────────────────────────────

  async function createLayout(payload: CreateLayoutPayload = {}): Promise<PageLayout | null> {
    saving.value = true
    try {
      const res = await api.createLayout(pageId.value, payload)
      populateFromItems(res.items)
      requiredLibs.value = res.required_libs
      return res.layout
    } finally {
      saving.value = false
    }
  }

  async function updateLayout(id: string, changes: UpdateLayoutPayload): Promise<PageLayout | null> {
    saving.value = true
    try {
      const res = await api.updateLayout(id, changes)
      const updated = res.layout
      if (layouts.value[id]) {
        layouts.value[id] = { ...layouts.value[id], ...updated }
      }
      // Also update the layout entry inside pageItems so the UI re-renders
      const idx = pageItems.value.findIndex((it) => it.id === id && it.type === 'layout')
      if (idx >= 0) {
        pageItems.value[idx] = { ...pageItems.value[idx], ...updated, type: 'layout' } as PageItem
      }
      return updated
    } finally {
      saving.value = false
    }
  }

  async function deleteLayout(id: string): Promise<void> {
    saving.value = true
    try {
      const res = await api.deleteLayout(id)
      populateFromItems(res.items)
      requiredLibs.value = res.required_libs
      if (selectedItemId.value === id) {
        selectedItemId.value = null
        selectedItemType.value = null
      }
    } finally {
      saving.value = false
    }
  }

  function replaceTree(data: { items: PageItem[]; required_libs: string[] }): void {
    populateFromItems(data.items)
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

  /**
   * Mutate a layout's local state immediately and queue a debounced API save.
   * Pass a partial UpdateLayoutPayload with any combination of label/display/columns/layout_config.
   */
  function updateLocalLayout(layoutId: string, changes: UpdateLayoutPayload): void {
    const l = layouts.value[layoutId]
    if (!l) return

    // Merge changes into the local layout object
    if (changes.label !== undefined) l.label = changes.label
    if (changes.display !== undefined) l.display = changes.display
    if (changes.columns !== undefined) l.columns = changes.columns
    if (changes.layout_config !== undefined) {
      l.layout_config = { ...l.layout_config, ...changes.layout_config }
    }

    // Also update the entry inside pageItems so the UI re-renders
    const idx = pageItems.value.findIndex(
      (it) => it.id === layoutId && it.type === 'layout'
    )
    if (idx >= 0) {
      pageItems.value[idx] = { ...pageItems.value[idx], ...l, type: 'layout' } as PageItem
    }

    // Merge pending changes for this layout
    const pending = pendingLayoutChanges.value[layoutId] ?? {}
    const merged: UpdateLayoutPayload = { ...pending }
    if (changes.label !== undefined) merged.label = changes.label
    if (changes.display !== undefined) merged.display = changes.display
    if (changes.columns !== undefined) merged.columns = changes.columns
    if (changes.layout_config !== undefined) {
      merged.layout_config = {
        ...(pending.layout_config ?? {}),
        ...changes.layout_config,
      }
    }
    pendingLayoutChanges.value[layoutId] = merged

    if (debounceLayoutTimer) clearTimeout(debounceLayoutTimer)
    debounceLayoutTimer = setTimeout(() => {
      const toSave = { ...pendingLayoutChanges.value }
      pendingLayoutChanges.value = {}
      debounceLayoutTimer = null

      for (const [id, payload] of Object.entries(toSave)) {
        api.updateLayout(id, payload).catch((e) =>
          console.error('Debounced layout save failed:', e)
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
    layouts,
    pageItems,
    rootOrder,
    selectedItemId,
    selectedItemType,
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
    selectedLayout,
    selectedBlockId,
    childrenOf,
    isWidgetDirty,
    columnTargets,

    // Actions
    loadTree,
    replaceTree,
    reloadTree,
    selectItem,
    selectBlock,
    setMode,
    createWidget,
    updateWidget,
    updateLocalConfig,
    deleteWidget,
    copyWidget,
    reorderWidgets,
    createLayout,
    updateLayout,
    updateLocalLayout,
    deleteLayout,
    refreshPreview,
    uploadImage,
    removeImage,
    updateLocalStyleConfig,
    updateLocalQueryConfig,
    saveColorSwatches: saveColorSwatchesAction,
  }
})
