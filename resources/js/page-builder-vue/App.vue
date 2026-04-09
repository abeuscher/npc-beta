<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { configure } from './api'
import type { BootstrapData } from './types'
import { useEditorStore } from './stores/editor'
import EditorToolbar from './components/EditorToolbar.vue'
import PreviewCanvas from './components/PreviewCanvas.vue'
import InspectorPanel from './components/InspectorPanel.vue'

const props = defineProps<{
  bootstrap: BootstrapData
}>()

const store = useEditorStore()

async function handleWidgetCreated(e: Event) {
  const detail = (e as CustomEvent).detail ?? {}
  if (detail.pageId !== store.pageId) return
  const widgetId = detail.widgetId ?? null
  await store.reloadTree()
  if (widgetId) {
    store.selectBlock(widgetId)
  }
}

function handleTemplateSaved(e: Event) {
  const detail = (e as CustomEvent).detail ?? {}
  if (detail.pageId !== store.pageId) return
  // no-op for now — could show a notification in the future
}

onMounted(() => {
  configure(props.bootstrap.csrf_token, props.bootstrap.api_base_url)
  store.loadTree(props.bootstrap)

  // Event bridge: listen for Livewire mutations
  window.addEventListener('widget-created', handleWidgetCreated)
  window.addEventListener('template-saved', handleTemplateSaved)
})

onUnmounted(() => {
  window.removeEventListener('widget-created', handleWidgetCreated)
  window.removeEventListener('template-saved', handleTemplateSaved)
})
</script>

<template>
  <div class="vue-editor">
    <EditorToolbar />

    <div class="vue-editor__layout">
      <div class="vue-editor__main" style="min-width: 0">
        <PreviewCanvas />
      </div>
      <div class="vue-editor__inspector">
        <InspectorPanel />
      </div>
    </div>
  </div>
</template>

<style scoped>
.vue-editor {
  margin-top: 1.5rem;
  border: 2px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1rem;
  background: #fff;
}

.vue-editor__layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) min(32rem, 33%);
  gap: 1rem;
  align-items: start;
}

.vue-editor__inspector {
  position: sticky;
  top: 1rem;
  max-height: calc(100vh - 2rem);
  overflow-y: auto;
}

@media (max-width: 768px) {
  .vue-editor__layout {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
