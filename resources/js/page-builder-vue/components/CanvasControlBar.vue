<script setup lang="ts">
import { useEditorStore } from '../stores/editor'
import { viewportPresets } from '../composables/useViewport'

const store = useEditorStore()

// Viewport presets with SVG icon paths
const presetIcons: Record<number, string> = {
  1920: 'M4 5h16a1 1 0 011 1v8a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zM7 18h10',
  1024: 'M7 4h10a1 1 0 011 1v14a1 1 0 01-1 1H7a1 1 0 01-1-1V5a1 1 0 011-1zm5 16v.01',
  375: 'M9 3h6a1 1 0 011 1v16a1 1 0 01-1 1H9a1 1 0 01-1-1V4a1 1 0 011-1zm3 18v.01',
}
</script>

<template>
  <div class="canvas-control-bar">
    <div class="canvas-control-bar__left"></div>

    <div class="canvas-control-bar__viewport">
      <span class="canvas-control-bar__viewport-label">Viewport:</span>
      <button
        v-for="vp in viewportPresets"
        :key="vp.width"
        type="button"
        class="canvas-control-bar__viewport-btn"
        :class="{
          'canvas-control-bar__viewport-btn--active': store.presetViewport === vp.width,
        }"
        :title="`${vp.label} (${vp.width}px)`"
        :aria-label="`${vp.label} viewport (${vp.width}px)`"
        :aria-pressed="store.presetViewport === vp.width"
        @click="store.setViewport(vp.width)"
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
      <span class="canvas-control-bar__viewport-size">{{ store.presetViewport }}px</span>
    </div>
  </div>
</template>

<style scoped>
.canvas-control-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
  padding: 0 0.25rem;
}

.canvas-control-bar__left {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.canvas-control-bar__viewport {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.canvas-control-bar__viewport-label {
  margin-right: 0.25rem;
  font-size: 0.75rem;
  color: #9ca3af;
}

.canvas-control-bar__viewport-btn {
  padding: 0.25rem;
  border-radius: 0.25rem;
  color: #9ca3af;
  transition: color 0.15s, background-color 0.15s;
  border: none;
  background: none;
  cursor: pointer;
}

.canvas-control-bar__viewport-btn:hover {
  color: #4b5563;
  background-color: #f3f4f6;
}

.canvas-control-bar__viewport-btn--active {
  color: var(--c-primary-700, #4338ca);
  background-color: var(--c-primary-100, #e0e7ff);
}

.canvas-control-bar__viewport-size {
  margin-left: 0.25rem;
  font-size: 0.75rem;
  color: #d1d5db;
  font-variant-numeric: tabular-nums;
}

html.dark .canvas-control-bar__viewport-label     { color: rgb(156 163 175); }
html.dark .canvas-control-bar__viewport-btn       { color: rgb(156 163 175); }
html.dark .canvas-control-bar__viewport-btn:hover { color: rgb(229 231 235); background-color: rgb(55 65 81); }
html.dark .canvas-control-bar__viewport-size      { color: rgb(107 114 128); }
</style>
