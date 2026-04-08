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
  <div ref="paneEl" class="preview-canvas">
    <!-- Viewport width toggle -->
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
}

.preview-canvas__empty {
  padding: 2rem;
  text-align: center;
  font-size: 0.875rem;
  color: #9ca3af;
}
</style>
