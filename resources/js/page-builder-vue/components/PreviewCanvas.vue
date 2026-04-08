<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, nextTick, computed } from 'vue'
import { useEditorStore } from '../stores/editor'
import { useViewport, viewportPresets } from '../composables/useViewport'
import { loadLibs, reinitAlpine } from '../composables/useLibraryLoader'
import PreviewRegion from './PreviewRegion.vue'

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
    const maxSort = store.rootWidgets.reduce((max, w) => Math.max(max, w.sort_order), -1)
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

function closeAddMenu(e: Event) {
  if (!(e.target as Element)?.closest('.add-block-dropdown')) {
    addMenuOpen.value = false
  }
}

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

// Re-init Alpine when preview HTML changes (any widget's preview_html)
watch(
  () => store.rootWidgets.map((w) => w.preview_html),
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
        <PreviewRegion
          v-for="widget in store.rootWidgets"
          :key="widget.id"
          :widget="widget"
        />

        <div
          v-if="store.rootWidgets.length === 0"
          class="preview-canvas__empty"
        >
          No blocks yet. Click <strong>+ Add Block</strong> to get started.
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
</style>
