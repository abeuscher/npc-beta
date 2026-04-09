<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, nextTick, computed } from 'vue'
import { useEditorStore } from '../stores/editor'
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

// Add block dropdown
const addMenuOpen = ref(false)

function openWidgetPicker(position: 'bottom' | 'above' | 'below') {
  addMenuOpen.value = false

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
    new CustomEvent('open-widget-picker', { detail: { insertPosition } })
  )
}

async function addColumnLayout(columns: number) {
  addMenuOpen.value = false
  const layout = await store.createLayout({
    label: `${columns} Column Layout`,
    display: 'grid',
    columns,
  })
  if (layout) {
    store.selectItem(layout.id, 'layout')
  }
}

function closeAddMenu(e: Event) {
  if (!(e.target as Element)?.closest('.add-block-dropdown')) {
    addMenuOpen.value = false
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

function onDragEnd() {
  const items = buildReorderPayload()
  store.reorderWidgets(items)
}

// Allow widgets and layouts at root, but reject layouts being dropped into other places
const rootPutFilter = (_to: any, _from: any, _dragEl: HTMLElement) => true

const isNarrowViewport = computed(() => presetViewport.value < 1920)

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
  document.addEventListener('click', closeAddMenu)
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
  document.removeEventListener('click', closeAddMenu)
  resizeObserver?.disconnect()
})

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
    <!-- Toolbar: add block + viewport toggle -->
    <div class="preview-canvas__viewport-bar">
      <!-- Add Block dropdown (left) -->
      <div class="add-block-dropdown">
        <button
          type="button"
          class="add-block-dropdown__trigger"
          @click.stop="addMenuOpen = !addMenuOpen"
        >
          + Add Block
        </button>
        <div v-show="addMenuOpen" class="add-block-dropdown__menu">
          <button
            type="button"
            class="add-block-dropdown__item"
            @click="openWidgetPicker('bottom')"
          >
            Insert at bottom
          </button>
          <button
            type="button"
            class="add-block-dropdown__item"
            :class="{ 'add-block-dropdown__item--disabled': !store.selectedWidget }"
            :disabled="!store.selectedWidget"
            @click="openWidgetPicker('above')"
          >
            Insert above selected
          </button>
          <button
            type="button"
            class="add-block-dropdown__item"
            :class="{ 'add-block-dropdown__item--disabled': !store.selectedWidget }"
            :disabled="!store.selectedWidget"
            @click="openWidgetPicker('below')"
          >
            Insert below selected
          </button>
          <div class="add-block-dropdown__divider"></div>
          <div class="add-block-dropdown__heading">Column Layout</div>
          <button
            type="button"
            class="add-block-dropdown__item"
            @click="addColumnLayout(2)"
          >
            + 2 columns
          </button>
          <button
            type="button"
            class="add-block-dropdown__item"
            @click="addColumnLayout(3)"
          >
            + 3 columns
          </button>
          <button
            type="button"
            class="add-block-dropdown__item"
            @click="addColumnLayout(4)"
          >
            + 4 columns
          </button>
        </div>
      </div>

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
          ? 'height: calc(100vh - 16rem); overflow-y: auto; display: flex; justify-content: center; background: #f3f4f6;'
          : 'min-height: 400px; overflow: hidden;'
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
          No blocks yet. Click <strong>+ Add Block</strong> to get started.
        </div>

        <button
          v-else
          type="button"
          class="preview-canvas__add-bottom"
          @click="openWidgetPicker('bottom')"
        >
          + Add widget
        </button>
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

/* Add Block dropdown */
.add-block-dropdown {
  position: relative;
  margin-right: auto;
}

.add-block-dropdown__trigger {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
  font-weight: 600;
  border-radius: 0.375rem;
  border: none;
  background: var(--c-primary-600, #4f46e5);
  color: #fff;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.add-block-dropdown__trigger:hover {
  background: var(--c-primary-500, #6366f1);
}

.add-block-dropdown__menu {
  position: absolute;
  top: 100%;
  left: 0;
  z-index: 20;
  margin-top: 0.25rem;
  min-width: 12rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.375rem;
  background: #fff;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.add-block-dropdown__item {
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

.add-block-dropdown__item:hover:not(:disabled) {
  background: #f3f4f6;
}

.add-block-dropdown__item--disabled {
  color: #d1d5db;
  cursor: not-allowed;
}

.add-block-dropdown__divider {
  height: 1px;
  background: #e5e7eb;
  margin: 0.25rem 0;
}

.add-block-dropdown__heading {
  padding: 0.375rem 0.75rem 0.125rem;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #9ca3af;
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
  overflow: hidden;
}

.preview-canvas__empty {
  padding: 2rem;
  text-align: center;
  font-size: 0.875rem;
  color: #9ca3af;
}

.preview-canvas__add-bottom {
  display: block;
  width: 100%;
  margin-top: 1rem;
  padding: 1.5rem;
  font-size: 2rem;
  font-weight: 600;
  color: #4f46e5;
  background: #fff;
  border: 2px dashed #c7d2fe;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: background-color 0.15s, border-color 0.15s;
}

.preview-canvas__add-bottom:hover {
  background: #eef2ff;
  border-color: #818cf8;
}
</style>
