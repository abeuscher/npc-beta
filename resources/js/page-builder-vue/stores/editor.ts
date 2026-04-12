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
  ThemePaletteEntry,
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

  // Theme palette (resolved colors from the active template, sourced from bootstrap data)
  const themePalette = ref<ThemePaletteEntry[]>([])

  // UI state
  const saving = ref(false)
  // True while a drag is in progress anywhere in the editor (root canvas or column slot).
  // Consumed by LayoutRegion to disable "+ Add widget" pointer events so the button
  // doesn't intercept drops when an empty slot is the target.
  const dragging = ref(false)

  // Debounced save state
  let debounceSaveTimer: ReturnType<typeof setTimeout> | null = null
  const pendingConfigChanges = ref<Record<string, Record<string, any>>>({})

  // Debounced layout save state
  let debounceLayoutTimer: ReturnType<typeof setTimeout> | null = null
  const pendingLayoutChanges = ref<Record<string, UpdateLayoutPayload>>({})

  // Per-widget preview refresh tracking (counter, abort, indicator stages, errors)
  const refreshCounts = ref<Record<string, number>>({})
  const abortControllers = new Map<string, AbortController>()
  const blurTimers = new Map<string, ReturnType<typeof setTimeout>>()
  const spinnerTimers = new Map<string, ReturnType<typeof setTimeout>>()
  const indicatorStage = ref<Record<string, 0 | 1 | 2>>({})
  const previewErrors = ref<Record<string, string | null>>({})

  const BLUR_DELAY_MS = 250
  const SPINNER_DELAY_MS = 500
  const DEBOUNCE_SAVE_MS = 350

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
    themePalette.value = data.theme_palette ?? []

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
        Object.assign(widgets.value[id], updated)
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
        Object.assign(layouts.value[id], updated)
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
      const res = await api.getPreview(id, controller.signal)
      if (widgets.value[id]) {
        widgets.value[id].preview_html = res.html
      }
      dirtyWidgets.value.delete(id)
      previewErrors.value[id] = null
    } catch (e: any) {
      if (e?.name === 'AbortError') {
        // Superseded by a newer refresh — silent.
        return
      }
      const message =
        e instanceof api.ApiError
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

  function payloadAffectsPreview(payload: UpdateWidgetPayload): boolean {
    // Label-only edits don't change rendered HTML, so skip the auto-refresh.
    return (
      payload.config !== undefined ||
      payload.appearance_config !== undefined ||
      payload.query_config !== undefined
    )
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
        updateWidget(id, payload)
          .then(() => {
            if (payloadAffectsPreview(payload)) {
              refreshPreview(id)
            }
          })
          .catch((e) => console.error('Debounced save failed:', e))
      }
    }, DEBOUNCE_SAVE_MS)
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
      refreshPreview(widgetId)
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
      refreshPreview(widgetId)
    } finally {
      saving.value = false
    }
  }

  /**
   * Update a single nested path inside the widget's appearance_config and queue a debounced save.
   * The path is a dot-separated string, e.g. 'background.color', 'layout.full_width', 'layout.padding.top'.
   * Intermediate objects are created as needed; the rest of the bag is preserved.
   */
  function updateLocalAppearanceConfig(widgetId: string, path: string, value: any): void {
    const w = widgets.value[widgetId]
    if (!w) return

    const segments = path.split('.')
    const next: Record<string, any> = { ...(w.appearance_config ?? {}) }

    let cursor: Record<string, any> = next
    for (let i = 0; i < segments.length - 1; i++) {
      const seg = segments[i]
      cursor[seg] = { ...(cursor[seg] ?? {}) }
      cursor = cursor[seg]
    }
    cursor[segments[segments.length - 1]] = value

    w.appearance_config = next as any
    dirtyWidgets.value.add(widgetId)
    flushDebouncedSave(widgetId, { appearance_config: next as any })
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
    dirtyWidgets,
    requiredLibs,
    widgetTypes,
    requiredHandles,
    collections,
    tags,
    pages,
    events,
    saving,
    dragging,
    inlineImageUploadUrl,
    colorSwatches,
    themePalette,

    // Getters
    rootWidgets,
    selectedWidget,
    selectedLayout,
    selectedBlockId,
    childrenOf,
    isWidgetDirty,
    columnTargets,
    widgetRefreshing,
    widgetIndicatorStage,
    widgetPreviewError,

    // Actions
    loadTree,
    replaceTree,
    reloadTree,
    selectItem,
    selectBlock,
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
    updateLocalAppearanceConfig,
    updateLocalQueryConfig,
    saveColorSwatches: saveColorSwatchesAction,
  }
})
