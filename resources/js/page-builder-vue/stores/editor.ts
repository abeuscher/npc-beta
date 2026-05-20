import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useDebounceFn } from '@vueuse/core'
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
  EditorMode,
} from '../types'
import { createApiClient, type ApiClient } from '../api'
import { useDebouncedSave } from '../composables/useDebouncedSave'
import { useRefreshPreview } from '../composables/useRefreshPreview'
import { useUploadActions } from '../composables/useUploadActions'
import { usePresetActions } from '../composables/usePresetActions'

export const useEditorStore = defineStore('editor', () => {
  // Core data
  const mode = ref<EditorMode>('page')
  const allowedAppearanceFields = ref<string[]>([])
  const allowedWidgetHandles = ref<string[]>([])
  const roleLabel = ref('')
  const viewLabel = ref('')
  const recordTypeLabel = ref('')
  const ownerId = ref('')
  const pageId = ref('')
  const pageType = ref('default')
  const pageTitle = ref('')
  const pageAuthor = ref('')
  const pageStatus = ref('draft')
  const pageUrl = ref('')
  const pageTags = ref<string[]>([])
  const detailsUrl = ref<string | null>(null)
  const widgets = ref<Record<string, Widget>>({}) // flat map of all widgets (root + inside layouts)
  const layouts = ref<Record<string, PageLayout>>({}) // flat map of layouts
  const pageItems = ref<PageItem[]>([]) // ordered merged page flow
  const rootOrder = ref<string[]>([]) // root widget IDs only — kept for backward compat with PreviewCanvas drag-end
  const selectedItemId = ref<string | null>(null)
  const selectedItemType = ref<'widget' | 'layout' | null>(null)

  // Inspector tab state — lives on the store so it persists across widget
  // selection changes within a single page-builder session. First load lands
  // on 'content' / 'background' (index-0 tabs in InspectorPanel).
  const inspectorTopTab = ref<'content' | 'presets' | 'widget-settings'>('content')
  const inspectorBottomTab = ref<'background' | 'text' | 'spacing'>('background')
  const layoutInspectorTab = ref<'column-settings' | 'margin-padding' | 'background'>('column-settings')

  // Per-instance API client. Each Vue app creates its own client so the
  // owner-scoped baseUrl can't collide when two page-builders live on the
  // same page load (e.g. template header + footer tabs). See api.ts.
  const apiClient = ref<ApiClient | null>(null)

  function configureApi(bootstrap: BootstrapData): void {
    apiClient.value = createApiClient(bootstrap.csrf_token, bootstrap.api_base_url, bootstrap.api_lookup_url)
  }

  function requireApi(): ApiClient {
    if (!apiClient.value) throw new Error('editor store: api client not configured — call configureApi(bootstrap) first')
    return apiClient.value
  }

  // Preview state
  const dirtyWidgets = ref<Set<string>>(new Set())
  const requiredLibs = ref<string[]>([])

  // The widget whose in-place text editor is currently active. While set,
  // the post-save preview-refresh echo is suppressed for that widget so the
  // live editor isn't wiped mid-keystroke (text edits only — structural
  // edits never set this and always re-render). Mirrors the RichTextField
  // dirty-guard, applied to the v-html refresh instead of a modelValue watch.
  const inlineActiveWidgetId = ref<string | null>(null)

  // ── Active-editor handle (session 305 §A / docs/inline-formatting-toolbar-spec.md)
  // The single shared reactive value the inline formatting toolbar will
  // read to drive whichever rich-text Quill instance is currently active.
  // useInlineEdit publishes here on richtext activation and clears on
  // teardown. Exactly one editor active at a time — what makes the 137
  // multi-region floating-toolbar conflict structurally impossible.
  // Plaintext (contenteditable) nodes never publish — nothing to format.
  // NOTE: the next session extends this record to the full §A4 shape
  // ({ quill, hostEl, widgetId, configPath, getRect }); the current stub
  // is the minimal foundation.
  const activeInlineEditor = ref<{ quill: any; widgetId: string; path: string } | null>(null)

  function setActiveInlineEditor(payload: { quill: any; widgetId: string; path: string }): void {
    activeInlineEditor.value = payload
  }

  // Clear only if the caller's instance is still the published one — a late
  // teardown from a superseded editor must not wipe a newer active editor.
  function clearActiveInlineEditor(quill: any): void {
    if (activeInlineEditor.value && activeInlineEditor.value.quill === quill) {
      activeInlineEditor.value = null
    }
  }

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

  // Heroicons manifest URL (from bootstrap data, used by RichTextField's
  // toolbar heroicon picker — empty string disables the toolbar button)
  const heroiconsUrl = ref('')

  // Theme editor URL (from bootstrap data, used by RichTextField "edit site styles" link)
  const themeEditorUrl = ref('')

  // Color swatches (shared across all color picker fields)
  const colorSwatches = ref<string[]>([])

  // Theme palette (resolved colors from the active template, sourced from bootstrap data)
  const themePalette = ref<ThemePaletteEntry[]>([])

  // Resolved theme typography families (session 305 §6.3): the inline
  // formatting toolbar's text-style menu renders Paragraph + H1–H6 rows
  // in the theme's actual fonts. Both are CSS font-family stacks, set
  // from bootstrap data and fall back to a safe system stack if absent.
  const themeHeadingFamily = ref<string>("'Inter', system-ui, sans-serif")
  const themeBodyFamily = ref<string>("'Inter', system-ui, sans-serif")

  // UI state
  const saving = ref(false)
  // True while a drag is in progress anywhere in the editor (root canvas or column slot).
  // Consumed by LayoutRegion to disable "+ Add widget" pointer events so the button
  // doesn't intercept drops when an empty slot is the target.
  const dragging = ref(false)

  // Debounced layout save state
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

  // ── Composable wiring (forward-referenced for circular deps) ───────────
  // useDebouncedSave's afterSave callback references refreshPreview, which
  // in turn awaits flushPendingSaves. Both reach for each other across the
  // wiring boundary, so we capture them via a forward `let` and assign once
  // both composables are constructed. The closures don't fire until user
  // interaction, by which point both sides are wired.
  let refreshPreviewRef: ((id: string) => Promise<void>) | null = null
  let flushPendingSavesRef: (() => Promise<void>) | null = null

  function payloadAffectsPreview(payload: UpdateWidgetPayload): boolean {
    // Label-only edits don't change rendered HTML, so skip the auto-refresh.
    return (
      payload.config !== undefined ||
      payload.appearance_config !== undefined ||
      payload.query_config !== undefined
    )
  }

  // ── Actions ────────────────────────────────────────────────────────────

  function loadTree(data: BootstrapData): void {
    mode.value = data.mode ?? 'page'
    allowedAppearanceFields.value = data.allowed_appearance_fields ?? []
    allowedWidgetHandles.value = data.allowed_widget_handles ?? []
    roleLabel.value = data.role_label ?? ''
    viewLabel.value = data.view_label ?? ''
    recordTypeLabel.value = data.record_type_label ?? ''
    ownerId.value = data.owner_id ?? data.page_id
    pageId.value = data.page_id
    pageType.value = data.page_type
    pageTitle.value = data.page_title ?? ''
    pageAuthor.value = data.page_author ?? ''
    pageStatus.value = data.page_status ?? 'draft'
    pageUrl.value = data.page_url ?? ''
    pageTags.value = data.page_tags ?? []
    detailsUrl.value = data.details_url ?? null
    widgetTypes.value = data.widget_types
    requiredHandles.value = data.required_handles
    collections.value = data.collections
    tags.value = data.tags
    pages.value = data.pages
    events.value = data.events
    inlineImageUploadUrl.value = data.inline_image_upload_url ?? ''
    heroiconsUrl.value = data.heroicons_url ?? ''
    themeEditorUrl.value = data.theme_editor_url ?? ''
    colorSwatches.value = data.color_swatches ?? []
    themePalette.value = data.theme_palette ?? []
    if (data.theme_heading_family) themeHeadingFamily.value = data.theme_heading_family
    if (data.theme_body_family)    themeBodyFamily.value    = data.theme_body_family

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

    if (selectedItemId.value && !widgetMap[selectedItemId.value] && !layoutMap[selectedItemId.value]) {
      selectedItemId.value = null
      selectedItemType.value = null
    }
  }

  function selectFirstRootItemIfNone(): void {
    if (selectedItemId.value) return
    const first = pageItems.value[0]
    if (!first) return
    selectedItemId.value = first.id
    selectedItemType.value = first.type === 'layout' ? 'layout' : 'widget'
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
      const res = await requireApi().createWidget(payload)
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
      const res = await requireApi().updateWidget(id, changes)
      const updated = res.widget
      const local = widgets.value[id]
      if (local) {
        // Server-authoritative fields are always merged. The user-mutable
        // fields (config, appearance_config, query_config, label) are skipped
        // when the widget is currently dirty, because the local copy is by
        // definition newer than the response — the server is just confirming
        // "I received what you sent." If the widget is not dirty, the
        // response is authoritative and a normal merge happens.
        const isDirty = dirtyWidgets.value.has(id)
        const skipKeys = isDirty
          ? new Set(['config', 'appearance_config', 'query_config', 'label'])
          : new Set<string>()
        for (const [key, value] of Object.entries(updated)) {
          if (skipKeys.has(key)) continue
          ;(local as any)[key] = value
        }
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
      const res = await requireApi().deleteWidget(id)
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
      const res = await requireApi().copyWidget(id)
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
      const res = await requireApi().reorderWidgets(items)
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
      const res = await requireApi().createLayout(payload)
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
      const res = await requireApi().updateLayout(id, changes)
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
      const res = await requireApi().deleteLayout(id)
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
      const res = await requireApi().getWidgets()
      replaceTree(res)
    } catch (e) {
      console.error('Failed to reload widget tree:', e)
    }
  }

  // ── Composable wiring (post-CRUD so updateWidget is in scope) ──────────

  const debounced = useDebouncedSave({
    updateWidget: (id, payload) => updateWidget(id, payload),
    afterSave: (id, payload) => {
      // Suppress the refresh echo for the actively inline-edited widget —
      // the v-html re-render would destroy the live editor. endInlineEdit()
      // does one reconciling refresh on blur/teardown instead.
      if (id === inlineActiveWidgetId.value) return
      if (payloadAffectsPreview(payload)) {
        refreshPreviewRef?.(id)
      }
    },
  })
  flushPendingSavesRef = debounced.flushPendingSaves

  const refresh = useRefreshPreview({
    widgets,
    dirtyWidgets,
    requireApi,
    flushPendingSaves: () => flushPendingSavesRef!(),
    inlineActiveWidgetId,
  })
  refreshPreviewRef = refresh.refreshPreview

  const uploads = useUploadActions({
    widgets,
    dirtyWidgets,
    saving,
    requireApi,
    refreshPreview: refresh.refreshPreview,
  })

  const presets = usePresetActions({
    widgets,
    dirtyWidgets,
    widgetTypes,
    requireApi,
    flushPendingSaves: debounced.flushPendingSaves,
    flushDebouncedSave: debounced.flushDebouncedSave,
  })

  // ── Local config / appearance / query / layout mutations ───────────────
  // Each route a debounced API save through `debounced.flushDebouncedSave`.

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
      debounced.flushDebouncedSave(widgetId, { label })
      return
    }

    if (key !== null) {
      w.config = { ...w.config, [key]: value }
      dirtyWidgets.value.add(widgetId)
      debounced.flushDebouncedSave(widgetId, { config: { ...w.config } })
    }
  }

  /**
   * Set a value at a dot-addressed config path (supports nesting through
   * repeater arrays, e.g. 'columns.2.attribute_rows.0.value') and queue a
   * debounced save. Arrays stay arrays — numeric path segments index a
   * list, non-numeric segments key an object. Flat keys ('content') are
   * just single-segment paths. This is the raw-config write inline editing
   * routes through; the rendered DOM is never serialized back.
   */
  function updateLocalConfigPath(widgetId: string, path: string, value: any): void {
    const w = widgets.value[widgetId]
    if (!w) return

    const segments = path.split('.')
    // Proxy-safe deep clone: w.config is a Vue reactive Proxy, which
    // structuredClone rejects (DataCloneError). Config is pure JSON data,
    // so a JSON round-trip clones it and preserves arrays as arrays.
    const next: Record<string, any> = JSON.parse(JSON.stringify(w.config ?? {}))

    let cursor: any = next
    for (let i = 0; i < segments.length - 1; i++) {
      const seg = segments[i]
      if (cursor[seg] === undefined || cursor[seg] === null) {
        cursor[seg] = /^\d+$/.test(segments[i + 1]) ? [] : {}
      }
      cursor = cursor[seg]
    }
    cursor[segments[segments.length - 1]] = value

    w.config = next
    dirtyWidgets.value.add(widgetId)
    debounced.flushDebouncedSave(widgetId, { config: { ...next } })
  }

  function beginInlineEdit(widgetId: string): void {
    inlineActiveWidgetId.value = widgetId
  }

  /**
   * Tear down an in-place edit session: clear the suppression flag, flush
   * any pending debounced save, then do exactly one reconciling preview
   * refresh so the rendered HTML re-derives from the saved raw config
   * (token substitution / inline-image rendering re-applied).
   */
  async function endInlineEdit(widgetId: string): Promise<void> {
    if (inlineActiveWidgetId.value === widgetId) {
      inlineActiveWidgetId.value = null
    }
    await debounced.flushPendingSaves()
    await refresh.refreshPreview(widgetId)
  }

  function clearAllOverrides(widgetId: string): void {
    const w = widgets.value[widgetId]
    if (!w) return
    w.config = {}
    dirtyWidgets.value.add(widgetId)
    debounced.flushDebouncedSave(widgetId, { config: {} })
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
    if (changes.appearance_config !== undefined) {
      l.appearance_config = changes.appearance_config as any
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
    if (changes.appearance_config !== undefined) {
      merged.appearance_config = changes.appearance_config
    }
    pendingLayoutChanges.value[layoutId] = merged

    flushPendingLayoutSaves()
  }

  /**
   * Update a single nested path inside a layout's appearance_config and queue
   * a debounced save. Path is dot-separated (e.g. 'background.color',
   * 'layout.padding.top'). Mirrors updateLocalAppearanceConfig for widgets.
   */
  function updateLocalLayoutAppearance(layoutId: string, path: string, value: any): void {
    const l = layouts.value[layoutId]
    if (!l) return

    const segments = path.split('.')
    const next: Record<string, any> = { ...(l.appearance_config ?? {}) }

    let cursor: Record<string, any> = next
    for (let i = 0; i < segments.length - 1; i++) {
      const seg = segments[i]
      cursor[seg] = { ...(cursor[seg] ?? {}) }
      cursor = cursor[seg]
    }
    cursor[segments[segments.length - 1]] = value

    updateLocalLayout(layoutId, { appearance_config: next as any })
  }

  const flushPendingLayoutSaves = useDebounceFn(() => {
    const toSave = { ...pendingLayoutChanges.value }
    pendingLayoutChanges.value = {}

    for (const [id, payload] of Object.entries(toSave)) {
      requireApi().updateLayout(id, payload).catch((e) =>
        console.error('Debounced layout save failed:', e)
      )
    }
  }, 500)

  /**
   * Update a single nested path inside the widget's appearance_config and queue a debounced save.
   * The path is a dot-separated string, e.g. 'background.color', 'layout.content_full_width', 'layout.padding.top'.
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
    debounced.flushDebouncedSave(widgetId, { appearance_config: next as any })
  }

  function updateLocalQueryConfig(
    widgetId: string,
    key: string,
    value: any
  ): void {
    const w = widgets.value[widgetId]
    if (!w) return

    w.query_config = { ...w.query_config, [key]: value }
    dirtyWidgets.value.add(widgetId)
    debounced.flushDebouncedSave(widgetId, { query_config: { ...w.query_config } })
  }

  async function saveColorSwatchesAction(swatches: string[]): Promise<void> {
    colorSwatches.value = swatches
    try {
      const res = await requireApi().saveColorSwatches(swatches)
      colorSwatches.value = res.swatches
    } catch (e) {
      console.error('Failed to save color swatches:', e)
    }
  }

  return {
    // State
    mode,
    allowedAppearanceFields,
    allowedWidgetHandles,
    roleLabel,
    viewLabel,
    recordTypeLabel,
    ownerId,
    pageId,
    pageType,
    pageTitle,
    pageAuthor,
    pageStatus,
    pageUrl,
    pageTags,
    detailsUrl,
    widgets,
    layouts,
    pageItems,
    rootOrder,
    selectedItemId,
    selectedItemType,
    inspectorTopTab,
    inspectorBottomTab,
    layoutInspectorTab,
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
    heroiconsUrl,
    themeEditorUrl,
    colorSwatches,
    themePalette,
    themeHeadingFamily,
    themeBodyFamily,

    // Getters
    rootWidgets,
    selectedWidget,
    selectedLayout,
    selectedBlockId,
    childrenOf,
    isWidgetDirty,
    columnTargets,
    widgetRefreshing: refresh.widgetRefreshing,
    widgetIndicatorStage: refresh.widgetIndicatorStage,
    widgetPreviewError: refresh.widgetPreviewError,

    // Actions
    configureApi,
    requireApi,
    loadTree,
    replaceTree,
    reloadTree,
    selectItem,
    selectBlock,
    selectFirstRootItemIfNone,
    createWidget,
    updateWidget,
    updateLocalConfig,
    updateLocalConfigPath,
    beginInlineEdit,
    endInlineEdit,
    inlineActiveWidgetId,
    activeInlineEditor,
    setActiveInlineEditor,
    clearActiveInlineEditor,
    clearAllOverrides,
    deleteWidget,
    copyWidget,
    reorderWidgets,
    createLayout,
    updateLayout,
    updateLocalLayout,
    updateLocalLayoutAppearance,
    deleteLayout,
    refreshPreview: refresh.refreshPreview,
    flushPendingSaves: debounced.flushPendingSaves,
    uploadImage: uploads.uploadImage,
    removeImage: uploads.removeImage,
    uploadAppearanceImage: uploads.uploadAppearanceImage,
    removeAppearanceImage: uploads.removeAppearanceImage,
    updateLocalAppearanceConfig,
    updateLocalQueryConfig,
    applyPreset: presets.applyPreset,
    saveDraftPreset: presets.saveDraftPreset,
    renameDraftPreset: presets.renameDraftPreset,
    deleteDraftPreset: presets.deleteDraftPreset,
    saveColorSwatches: saveColorSwatchesAction,
  }
})
