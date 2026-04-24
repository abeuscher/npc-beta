<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, nextTick, computed } from 'vue'
import { useEditorStore } from '../stores/editor'
import { scrollSelectionIntoCentre } from '../utils/focusScroll'
import { useViewport, viewportPresets } from '../composables/useViewport'
import { loadLibs, reinitAlpine } from '../composables/useLibraryLoader'
import PreviewRegion from './PreviewRegion.vue'
import LayoutRegion from './LayoutRegion.vue'
import draggable from 'vuedraggable'
import type { ReorderItem, PageItem, Widget, PageLayout } from '../types'

const store = useEditorStore()
const { presetViewport, zoomFactor, computeZoom, setViewport } = useViewport()

const paneEl = ref<HTMLElement | null>(null)
const scopeEl = ref<HTMLElement | null>(null)

// Columns dropdown
const columnsMenuOpen = ref(false)

// Layer explorer dropdown
const layerMenuOpen = ref(false)

const allItems = computed(() => {
  const items: { id: string; label: string; type: 'widget' | 'layout'; handle: string; nested: boolean }[] = []
  for (const item of store.pageItems) {
    if (item.type === 'layout') {
      items.push({ id: item.id, label: item.label || 'Column Layout', type: 'layout', handle: 'layout', nested: false })
      const slots = (item as any).slots ?? {}
      for (const col of Object.values(slots) as any[]) {
        for (const w of col) {
          items.push({ id: w.id, label: w.label || w.widget_type_handle, type: 'widget', handle: w.widget_type_handle, nested: true })
        }
      }
    } else {
      items.push({ id: item.id, label: (item as any).label || (item as any).widget_type_handle, type: 'widget', handle: (item as any).widget_type_handle, nested: false })
    }
  }
  return items
})

function selectLayerItem(id: string, type: 'widget' | 'layout') {
  layerMenuOpen.value = false
  if (type === 'widget') {
    store.selectBlock(id)
  } else {
    store.selectItem(id, 'layout')
  }
}

function openWidgetPicker(position: 'bottom' | 'above' | 'below') {
  columnsMenuOpen.value = false

  let insertPosition: number | null = null
  if (position === 'bottom') {
    const maxSort = store.pageItems.reduce(
      (max, it) => Math.max(max, (it as any).sort_order ?? 0),
      -1
    )
    insertPosition = maxSort + 1
  } else if (position === 'above' && store.selectedWidget) {
    insertPosition = store.selectedWidget.sort_order
  } else if (position === 'below' && store.selectedWidget) {
    insertPosition = store.selectedWidget.sort_order + 1
  }

  window.dispatchEvent(
    new CustomEvent('open-widget-picker', {
      detail: { insertPosition, ownerId: store.ownerId },
    })
  )
}

async function addColumnLayout(columns: number) {
  columnsMenuOpen.value = false
  const layout = await store.createLayout({
    label: `${columns} Column Layout`,
    display: 'grid',
    columns,
  })
  if (layout) {
    store.selectItem(layout.id, 'layout')
  }
}

function closeDropdowns(e: Event) {
  const target = e.target as Element
  if (!target?.closest('.preview-canvas__columns-dropdown')) {
    columnsMenuOpen.value = false
  }
  if (!target?.closest('.preview-canvas__layer-explorer')) {
    layerMenuOpen.value = false
  }
}

// Draggable list for root page flow (widgets and layouts interleaved).
// Using :list mode so vuedraggable mutates the array directly — required for
// shared groups so cross-list moves (root <-> column slots) work correctly.

// Build a complete reorder payload from the current pageItems state.
// Walks the merged page flow and emits one item per widget and one per layout.
function buildReorderPayload(): ReorderItem[] {
  const items: ReorderItem[] = []
  store.pageItems.forEach((item, rootIndex) => {
    if (item.type === 'widget') {
      items.push({
        id: item.id,
        type: 'widget',
        layout_id: null,
        column_index: null,
        sort_order: rootIndex,
      })
    } else {
      const layout = item as PageLayout & { type: 'layout' }
      items.push({
        id: layout.id,
        type: 'layout',
        sort_order: rootIndex,
      })
      const slots = (layout.slots ?? {}) as Record<string, Widget[]>
      for (const slotKey of Object.keys(slots)) {
        const slotIdx = parseInt(slotKey, 10)
        const slotWidgets = slots[slotKey] ?? []
        slotWidgets.forEach((w, j) => {
          items.push({
            id: w.id,
            type: 'widget',
            layout_id: layout.id,
            column_index: slotIdx,
            sort_order: j,
          })
        })
      }
    }
  })
  return items
}

function onDragStart() {
  store.dragging = true
}

function onDragEnd() {
  store.dragging = false
  const items = buildReorderPayload()
  store.reorderWidgets(items)
}

// Allow widgets and layouts at root, but reject layouts being dropped into other places
const rootPutFilter = (_to: any, _from: any, _dragEl: HTMLElement) => true

const isNarrowViewport = computed(() => presetViewport.value < 1920)

// CSS max-width for "contained" layouts inside the preview, mirroring the public
// .site-container breakpoints (which are keyed off real browser width). The preview
// uses a fixed pixel viewport, so we resolve the breakpoint up-front and pass it as
// a CSS variable for any contained block (e.g. a column layout with full_width: false).
const previewContainerMaxWidth = computed(() => {
  const w = presetViewport.value
  if (w >= 1400) return '1320px'
  if (w >= 1200) return '1140px'
  if (w >= 992) return '960px'
  if (w >= 768) return '720px'
  if (w >= 576) return '540px'
  return '100%'
})

function measurePane() {
  if (paneEl.value) {
    computeZoom(paneEl.value.getBoundingClientRect().width)
  }
}

function handleViewportChange(width: number) {
  setViewport(width)
  nextTick(() => {
    measurePane()
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        if (scopeEl.value) reinitAlpine(scopeEl.value)
      })
    })
  })
}

function preventNavigation(e: MouseEvent) {
  const anchor = (e.target as HTMLElement)?.closest?.('a')
  if (anchor) e.preventDefault()
}

// Viewport presets with SVG icon paths
const presetIcons: Record<number, string> = {
  1920: 'M4 5h16a1 1 0 011 1v8a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zM7 18h10',
  1024: 'M7 4h10a1 1 0 011 1v14a1 1 0 01-1 1H7a1 1 0 01-1-1V5a1 1 0 011-1zm5 16v.01',
  375: 'M9 3h6a1 1 0 011 1v16a1 1 0 01-1 1H9a1 1 0 01-1-1V4a1 1 0 011-1zm3 18v.01',
}

let resizeObserver: ResizeObserver | null = null

onMounted(async () => {
  document.addEventListener('click', closeDropdowns)
  measurePane()

  if (paneEl.value) {
    resizeObserver = new ResizeObserver(() => measurePane())
    resizeObserver.observe(paneEl.value)
  }

  await loadLibs(store.requiredLibs)

  requestAnimationFrame(() => {
    measurePane()
    requestAnimationFrame(() => {
      if (scopeEl.value) reinitAlpine(scopeEl.value)
    })
  })
})

onUnmounted(() => {
  document.removeEventListener('click', closeDropdowns)
  resizeObserver?.disconnect()
})

// Scroll the selected widget/layout to the viewport centre when selection
// changes. Uses nextTick so the selection class has a chance to render before
// the scroll fires; bails silently if the element is not in the DOM yet
// (e.g. during a tree reload).
watch(
  () => [store.selectedItemId, store.selectedItemType] as const,
  async ([id, type]) => {
    if (!id || !type) return
    await nextTick()
    scrollSelectionIntoCentre(id, type)
  }
)

// Re-init Alpine when any widget's preview HTML changes (root or inside layouts)
watch(
  () => Object.values(store.widgets).map((w) => w.preview_html),
  async () => {
    await loadLibs(store.requiredLibs)
    await nextTick()
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        if (scopeEl.value) reinitAlpine(scopeEl.value)
      })
    })
  },
  { deep: true }
)
</script>

<template>
  <div ref="paneEl" class="preview-canvas" style="min-width: 0">
    <!-- Viewport toggle bar -->
    <div class="preview-canvas__viewport-bar">
      <span class="preview-canvas__viewport-label">Viewport:</span>
      <button
        v-for="vp in viewportPresets"
        :key="vp.width"
        type="button"
        class="preview-canvas__viewport-btn"
        :class="{
          'preview-canvas__viewport-btn--active': presetViewport === vp.width,
        }"
        :title="`${vp.label} (${vp.width}px)`"
        @click="handleViewportChange(vp.width)"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-4 w-4"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            :d="presetIcons[vp.width]"
          />
        </svg>
      </button>
      <span class="preview-canvas__viewport-size">{{ presetViewport }}px</span>
    </div>

    <!-- Preview container -->
    <div
      class="preview-canvas__container"
      :style="
        isNarrowViewport
          ? 'display: flex; justify-content: center; background: #f3f4f6;'
          : ''
      "
    >
      <div
        ref="scopeEl"
        class="widget-preview-scope np-site"
        :style="{
          width: presetViewport + 'px',
          zoom: zoomFactor,
          transformOrigin: 'top left',
          flexShrink: isNarrowViewport ? 0 : undefined,
          '--np-preview-container-max-width': previewContainerMaxWidth,
        }"
        @click="preventNavigation"
        @submit.prevent
      >
        <draggable
          :list="store.pageItems"
          :group="{ name: 'page-items', pull: true, put: rootPutFilter }"
          item-key="id"
          :animation="200"
          :fallback-on-body="true"
          :swap-threshold="0.65"
          ghost-class="preview-region--ghost"
          handle=".preview-region__overlay, .layout-region__handle"
          @start="onDragStart"
          @end="onDragEnd"
        >
          <template #item="{ element }">
            <PreviewRegion v-if="element.type === 'widget'" :widget="element" />
            <LayoutRegion
              v-else-if="element.type === 'layout'"
              :layout="element"
              @drag-end="onDragEnd"
            />
          </template>
        </draggable>

        <div
          v-if="store.pageItems.length === 0"
          class="preview-canvas__empty"
        >
          No blocks yet. Click <strong>+ Widget</strong> below to get started.
        </div>
      </div>
    </div>

    <!-- Bottom action bar: + Widget, + Columns, block count -->
    <div class="preview-canvas__bottom-bar">
      <div class="preview-canvas__bottom-actions">
        <button
          type="button"
          class="preview-canvas__action-btn preview-canvas__action-btn--primary"
          @click="openWidgetPicker('bottom')"
        >
          + Widget
        </button>
        <div v-if="store.mode !== 'dashboard'" class="preview-canvas__columns-dropdown">
          <button
            type="button"
            class="preview-canvas__action-btn preview-canvas__action-btn--secondary"
            @click.stop="columnsMenuOpen = !columnsMenuOpen"
          >
            + Columns &#9662;
          </button>
          <div v-show="columnsMenuOpen" class="preview-canvas__columns-menu">
            <button type="button" class="preview-canvas__columns-item" @click="addColumnLayout(2)">2 columns</button>
            <button type="button" class="preview-canvas__columns-item" @click="addColumnLayout(3)">3 columns</button>
            <button type="button" class="preview-canvas__columns-item" @click="addColumnLayout(4)">4 columns</button>
          </div>
        </div>
      </div>
      <div class="preview-canvas__layer-explorer">
        <button
          type="button"
          class="preview-canvas__block-count"
          @click.stop="layerMenuOpen = !layerMenuOpen"
        >
          {{ allItems.length }} block(s) &#9662;
        </button>
        <div v-show="layerMenuOpen" class="preview-canvas__layer-menu">
          <button
            v-for="item in allItems"
            :key="item.id"
            type="button"
            class="preview-canvas__layer-item"
            :class="{
              'preview-canvas__layer-item--selected': store.selectedItemId === item.id,
              'preview-canvas__layer-item--nested': item.nested
            }"
            @click="selectLayerItem(item.id, item.type)"
          >
            <span class="preview-canvas__layer-type">{{ item.type === 'layout' ? '⊞' : '◻' }}</span>
            <span class="preview-canvas__layer-label">{{ item.label }}</span>
            <span class="preview-canvas__layer-handle">{{ item.handle }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.preview-canvas__viewport-bar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.25rem;
  margin-bottom: 0.5rem;
  padding: 0 0.25rem;
}

.preview-canvas__viewport-label {
  margin-right: 0.25rem;
  font-size: 0.75rem;
  color: #9ca3af;
}

.preview-canvas__viewport-btn {
  padding: 0.25rem;
  border-radius: 0.25rem;
  color: #9ca3af;
  transition: color 0.15s, background-color 0.15s;
  border: none;
  background: none;
  cursor: pointer;
}

.preview-canvas__viewport-btn:hover {
  color: #4b5563;
  background-color: #f3f4f6;
}

.preview-canvas__viewport-btn--active {
  color: var(--c-primary-700, #4338ca);
  background-color: var(--c-primary-100, #e0e7ff);
}

.preview-canvas__viewport-size {
  margin-left: 0.25rem;
  font-size: 0.75rem;
  color: #d1d5db;
  font-variant-numeric: tabular-nums;
}

.preview-canvas__container {
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
  background: #fff;
  padding-top: 1.5rem;
}

.preview-canvas__empty {
  padding: 2rem;
  text-align: center;
  font-size: 0.875rem;
  color: #9ca3af;
}

/* Bottom action bar */
.preview-canvas__bottom-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 0.75rem;
  padding: 0 0.25rem;
}

.preview-canvas__bottom-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.preview-canvas__action-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 600;
  border-radius: 0.5rem;
  border: none;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.preview-canvas__action-btn--primary {
  background: var(--c-primary-600, #4f46e5);
  color: #fff;
}

.preview-canvas__action-btn--primary:hover {
  background: var(--c-primary-500, #6366f1);
}

.preview-canvas__action-btn--secondary {
  background: #fff;
  color: #374151;
  border: 1px solid #d1d5db;
}

.preview-canvas__action-btn--secondary:hover {
  background: #f9fafb;
}

.preview-canvas__columns-dropdown {
  position: relative;
}

.preview-canvas__columns-menu {
  position: absolute;
  bottom: 100%;
  left: 0;
  z-index: 20;
  margin-bottom: 0.25rem;
  min-width: 8rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.375rem;
  background: #fff;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.preview-canvas__columns-item {
  display: block;
  width: 100%;
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
  text-align: left;
  border: none;
  background: none;
  color: #374151;
  cursor: pointer;
}

.preview-canvas__columns-item:hover {
  background: #f3f4f6;
}

.preview-canvas__layer-explorer {
  position: relative;
}

.preview-canvas__block-count {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
}

.preview-canvas__block-count:hover {
  background: #f3f4f6;
}

.preview-canvas__layer-menu {
  position: absolute;
  bottom: 100%;
  right: 0;
  margin-bottom: 0.25rem;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  min-width: 240px;
  max-height: 320px;
  overflow-y: auto;
  z-index: 50;
  padding: 0.25rem 0;
}

.preview-canvas__layer-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.375rem 0.75rem;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 0.8125rem;
  text-align: left;
  color: #374151;
}

.preview-canvas__layer-item:hover {
  background: #f3f4f6;
}

.preview-canvas__layer-item--selected {
  background: #eff6ff;
  font-weight: 600;
}

.preview-canvas__layer-item--nested {
  padding-left: 1.5rem;
}

.preview-canvas__layer-type {
  flex-shrink: 0;
  font-size: 0.75rem;
  opacity: 0.5;
}

.preview-canvas__layer-label {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.preview-canvas__layer-handle {
  font-size: 0.6875rem;
  color: #9ca3af;
  flex-shrink: 0;
}

html.dark .preview-canvas__viewport-label      { color: rgb(156 163 175); }
html.dark .preview-canvas__viewport-btn        { color: rgb(156 163 175); }
html.dark .preview-canvas__viewport-btn:hover  { color: rgb(229 231 235); background-color: rgb(55 65 81); }
html.dark .preview-canvas__viewport-size       { color: rgb(107 114 128); }
html.dark .preview-canvas__container           { background: rgb(31 41 55); border-color: rgb(55 65 81); }
html.dark .preview-canvas__empty               { color: rgb(156 163 175); }
</style>
