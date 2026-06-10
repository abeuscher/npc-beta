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
  <div
    class="canvas-control-bar"
    :class="{ 'canvas-control-bar--fullscreen': store.fullscreen }"
  >
    <div class="canvas-control-bar__left">
      <button
        type="button"
        class="canvas-control-bar__fullscreen-btn"
        :title="store.fullscreen ? 'Exit full screen' : 'Edit full screen'"
        :aria-label="store.fullscreen ? 'Exit full screen' : 'Edit full screen'"
        :aria-pressed="store.fullscreen"
        @click="store.toggleFullscreen()"
      >
        <svg
          v-if="!store.fullscreen"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="2"
          stroke="currentColor"
          class="h-4 w-4"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
        </svg>
        <svg
          v-else
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="2"
          stroke="currentColor"
          class="h-4 w-4"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25" />
        </svg>
        <span class="canvas-control-bar__fullscreen-label">{{ store.fullscreen ? 'Exit full screen' : 'Full screen' }}</span>
      </button>
    </div>

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

.canvas-control-bar__fullscreen-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 500;
  border-radius: 0.25rem;
  color: #6b7280;
  border: 1px solid #d1d5db;
  background: none;
  cursor: pointer;
  transition: color 0.15s, background-color 0.15s, border-color 0.15s;
}

.canvas-control-bar__fullscreen-btn:hover {
  color: var(--c-primary-700, #4338ca);
  background-color: #f3f4f6;
  border-color: var(--c-primary-300, #a5b4fc);
}

/* Full-screen: the bar leaves the editor flow and pins to the top-left of
   the screen as a compact floating cluster — the one piece of chrome that
   stays put while the canvas scrolls beneath. z-index sits above the
   overlay (40) and below the modals (50). */
.canvas-control-bar--fullscreen {
  position: fixed;
  top: 0.625rem;
  left: 0.75rem;
  z-index: 45;
  margin-bottom: 0;
  gap: 0.75rem;
  padding: 0.25rem 0.5rem;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
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
html.dark .canvas-control-bar__fullscreen-btn        { color: rgb(156 163 175); border-color: rgb(75 85 99); }
html.dark .canvas-control-bar__fullscreen-btn:hover  { color: rgb(229 231 235); background-color: rgb(55 65 81); }
html.dark .canvas-control-bar--fullscreen            { background: rgb(17 24 39); border-color: rgb(55 65 81); }
</style>
