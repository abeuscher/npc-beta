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

function openSaveTemplateModal() {
  window.dispatchEvent(
    new CustomEvent('open-save-template-modal', {
      detail: { pageId: store.pageId },
    })
  )
}

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

    <div v-if="store.rootWidgets.length > 0" class="vue-editor__footer">
      <button
        type="button"
        class="vue-editor__save-template-btn"
        @click="openSaveTemplateModal"
      >
        Save as Template
      </button>
    </div>
  </div>
</template>

<style scoped>
.vue-editor {
  border: 2px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1rem;
  background: #fff;
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
  max-height: calc(100vh - 2rem);
  overflow-y: auto;
}

.vue-editor__footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

.vue-editor__save-template-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  border-radius: 0.5rem;
  border: 1px solid #d1d5db;
  background: #fff;
  color: #374151;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.vue-editor__save-template-btn:hover {
  background: #f9fafb;
}

@media (max-width: 768px) {
  .vue-editor__layout {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
