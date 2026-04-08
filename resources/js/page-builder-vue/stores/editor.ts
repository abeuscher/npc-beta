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

  // UI state
  const saving = ref(false)

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

  async function uploadImage(widgetId: string, key: string, file: File): Promise<void> {
    saving.value = true
    try {
      const res = await api.uploadImage(widgetId, key, file)
      if (widgets.value[widgetId]) {
        const w = widgets.value[widgetId]
        w.config = { ...w.config, [key]: res.media_id }
      }
      dirtyWidgets.value.add(widgetId)
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
      }
      dirtyWidgets.value.add(widgetId)
    } finally {
      saving.value = false
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
    deleteWidget,
    copyWidget,
    reorderWidgets,
    refreshPreview,
    uploadImage,
    removeImage,
  }
})
