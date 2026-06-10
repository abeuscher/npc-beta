<script setup lang="ts">
import { onMounted, onUnmounted, watch } from 'vue'
import type { BootstrapData } from './types'
import { useEditorStore } from './stores/editor'
import EditorToolbar from './components/EditorToolbar.vue'
import CanvasControlBar from './components/CanvasControlBar.vue'
import PreviewCanvas from './components/PreviewCanvas.vue'
import InspectorPanel from './components/InspectorPanel.vue'
import InlineFormatToolbar from './components/InlineFormatToolbar.vue'
import DedupPromptModal from './components/DedupPromptModal.vue'

const props = defineProps<{
  bootstrap: BootstrapData
}>()

const store = useEditorStore()

function saveChanges() {
  window.dispatchEvent(
    new CustomEvent('page-builder-save', {
      detail: { ownerId: store.ownerId },
    })
  )
}

async function handleWidgetCreated(e: Event) {
  const detail = (e as CustomEvent).detail ?? {}
  if (detail.ownerId !== store.ownerId) return
  const widgetId = detail.widgetId ?? null
  await store.reloadTree()
  if (widgetId) {
    store.selectBlock(widgetId)
  }
}

function handleTemplateSaved(e: Event) {
  const detail = (e as CustomEvent).detail ?? {}
  if (detail.ownerId !== store.ownerId) return
  // no-op for now — could show a notification in the future
}

// Full-screen lifts the editor out of the page as a fixed overlay that
// scrolls internally; lock the document scroll behind it so there's a
// single scrollbar. Restored on exit and on unmount (e.g. Livewire nav).
watch(
  () => store.fullscreen,
  (on) => {
    document.body.style.overflow = on ? 'hidden' : ''
  }
)

onMounted(() => {
  store.configureApi(props.bootstrap)
  store.loadTree(props.bootstrap)
  store.selectFirstRootItemIfNone()

  // Event bridge: listen for Livewire mutations
  window.addEventListener('widget-created', handleWidgetCreated)
  window.addEventListener('template-saved', handleTemplateSaved)
})

onUnmounted(() => {
  window.removeEventListener('widget-created', handleWidgetCreated)
  window.removeEventListener('template-saved', handleTemplateSaved)
  document.body.style.overflow = ''
})
</script>

<template>
  <div class="vue-editor" :class="{ 'vue-editor--fullscreen': store.fullscreen }">
    <EditorToolbar v-if="!store.fullscreen" />

    <CanvasControlBar />

    <div class="vue-editor__layout">
      <div class="vue-editor__main" style="min-width: 0">
        <PreviewCanvas />
      </div>
      <div
        class="vue-editor__inspector"
        :class="{ 'vue-editor__inspector--open': store.fullscreenInspectorOpen }"
      >
        <button
          v-if="store.fullscreen"
          type="button"
          class="vue-editor__inspector-tab"
          :title="store.fullscreenInspectorOpen ? 'Collapse inspector' : 'Expand inspector'"
          :aria-label="store.fullscreenInspectorOpen ? 'Collapse inspector' : 'Expand inspector'"
          :aria-expanded="store.fullscreenInspectorOpen"
          @click="store.fullscreenInspectorOpen = !store.fullscreenInspectorOpen"
        >
          <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
            <path
              :d="store.fullscreenInspectorOpen ? 'M6 4 L10 8 L6 12' : 'M10 4 L6 8 L10 12'"
              fill="none"
              stroke="currentColor"
              stroke-width="1.75"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
          <span class="vue-editor__inspector-tab-label">Inspector</span>
        </button>
        <InspectorPanel />
      </div>
    </div>

    <div v-if="store.mode === 'page'" class="vue-editor__footer">
      <button
        type="button"
        class="vue-editor__save-btn"
        @click="saveChanges"
      >
        Save changes
      </button>
    </div>

    <!-- Spec §A1/§A2/§C15: exactly one toolbar, created with the
         page-builder, never destroyed at runtime, mounted into a fixed
         layer at app root via <Teleport>; never inside any v-html. -->
    <InlineFormatToolbar />

    <DedupPromptModal />
  </div>
</template>

<style scoped>
.vue-editor {
  border: 2px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1rem;
  background: #fff;
}

html.dark .vue-editor {
  background: rgb(17 24 39);
  border-color: rgb(55 65 81);
  color: rgb(229 231 235);
}

.vue-editor__layout {
  display: grid;
  grid-template-columns: minmax(0, 3fr) min(28rem, 25%);
  gap: 1rem;
  align-items: start;
}

.vue-editor__inspector {
  position: sticky;
  top: 1rem;
  height: calc(100vh - 2rem);
}

.vue-editor__footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

.vue-editor__save-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.5rem 1.25rem;
  font-size: 0.875rem;
  font-weight: 600;
  border-radius: 0.5rem;
  border: 1px solid transparent;
  background: rgb(var(--primary-600, 37 99 235));
  color: #fff;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.vue-editor__save-btn:hover {
  background: rgb(var(--primary-500, 59 130 246));
}

@media (max-width: 768px) {
  .vue-editor__layout {
    grid-template-columns: minmax(0, 1fr);
  }
}

/* ── Full-screen mode ──────────────────────────────────────────────────────
   The editor lifts out of the Filament form as a fixed overlay painted over
   the admin chrome. z-index 40 — below the body-teleported modals (50) and
   the inline format toolbar (60), above the admin shell. The canvas pane
   takes the full width; the existing ResizeObserver re-derives the zoom
   from the wider pane, so the preview re-scales on its own. */
.vue-editor--fullscreen {
  position: fixed;
  inset: 0;
  z-index: 40;
  border: none;
  border-radius: 0;
  padding: 3.25rem 1rem 1rem;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.vue-editor--fullscreen .vue-editor__layout {
  grid-template-columns: minmax(0, 1fr);
}

/* Inspector becomes a right-edge drawer: collapsed by default (translated
   fully off-screen except the edge tab), sliding in OVER the canvas so the
   pane width — and with it the zoom — doesn't bounce on every toggle.
   No overflow here: InspectorPanel's panes own their internal scrolling,
   and clipping would also cut off the edge tab hanging outside the box. */
.vue-editor--fullscreen .vue-editor__inspector {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(28rem, 90vw);
  height: auto;
  z-index: 44;
  padding: 0 1rem 1rem;
  background: #fff;
  border-left: 1px solid #e5e7eb;
  transform: translateX(100%);
  transition: transform 0.2s ease;
}

.vue-editor--fullscreen .vue-editor__inspector--open {
  transform: translateX(0);
  box-shadow: -4px 0 16px rgba(0, 0, 0, 0.08);
}

.vue-editor__inspector-tab {
  position: absolute;
  left: -2.5rem;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
  width: 2.5rem;
  padding: 0.625rem 0;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-right: none;
  border-radius: 0.5rem 0 0 0.5rem;
  box-shadow: -2px 0 8px rgba(0, 0, 0, 0.08);
  color: #6b7280;
  cursor: pointer;
}

.vue-editor__inspector-tab:hover {
  color: var(--c-primary-700, #4338ca);
}

.vue-editor__inspector-tab-label {
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  writing-mode: vertical-rl;
}

/* Keep Save reachable while full-screen; it slides left out of the way
   when the inspector drawer opens. */
.vue-editor--fullscreen .vue-editor__footer {
  position: fixed;
  right: 1.25rem;
  bottom: 1.25rem;
  z-index: 44;
  margin: 0;
  padding: 0;
  border: none;
  transition: right 0.2s ease;
}

.vue-editor--fullscreen:has(.vue-editor__inspector--open) .vue-editor__footer {
  right: calc(min(28rem, 90vw) + 1.25rem);
}

html.dark .vue-editor--fullscreen .vue-editor__inspector {
  background: rgb(17 24 39);
  border-color: rgb(55 65 81);
}

html.dark .vue-editor__inspector-tab {
  background: rgb(17 24 39);
  border-color: rgb(55 65 81);
  color: rgb(156 163 175);
}

html.dark .vue-editor__inspector-tab:hover {
  color: rgb(229 231 235);
}
</style>
