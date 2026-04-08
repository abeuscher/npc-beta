<script setup lang="ts">
import { onMounted, onUnmounted, nextTick } from 'vue'
import { configure } from './api'
import type { BootstrapData } from './types'
import { useEditorStore } from './stores/editor'
import EditorToolbar from './components/EditorToolbar.vue'
import PreviewCanvas from './components/PreviewCanvas.vue'
import BlockListPoc from './components/BlockListPoc.vue'
import InspectorPanel from './components/InspectorPanel.vue'

const store = useEditorStore()

function handleTreeUpdated() {
  store.reloadTree()
}

function handleBlockSelected(e: Event) {
  const blockId = (e as CustomEvent).detail?.blockId ?? ''
  store.selectBlock(blockId || null)

  if (blockId) {
    nextTick(() => {
      const el = document.querySelector(`[data-widget-id="${blockId}"]`)
      el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    })
  }
}

function handleTemplateSaved() {
  // no-op for now — could show a notification in the future
}

onMounted(() => {
  const el = document.getElementById('page-builder-app')
  if (!el) return

  const raw = el.getAttribute('data-bootstrap')
  if (!raw) return

  const data: BootstrapData = JSON.parse(raw)

  configure(data.csrf_token, data.api_base_url)
  store.loadTree(data)

  // Event bridge: listen for Livewire mutations
  window.addEventListener('widget-tree-updated', handleTreeUpdated)
  window.addEventListener('block-selected', handleBlockSelected)
  window.addEventListener('template-saved', handleTemplateSaved)
})

onUnmounted(() => {
  window.removeEventListener('widget-tree-updated', handleTreeUpdated)
  window.removeEventListener('block-selected', handleBlockSelected)
  window.removeEventListener('template-saved', handleTemplateSaved)
})
</script>

<template>
  <div class="vue-editor">
    <EditorToolbar />

    <div class="vue-editor__layout">
      <div class="vue-editor__main" style="min-width: 0">
        <PreviewCanvas v-if="store.editorMode === 'edit'" />
        <BlockListPoc v-if="store.editorMode === 'handles'" />
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
  grid-template-columns: 2fr 1fr;
  gap: 1rem;
  align-items: start;
}

.vue-editor__inspector {
  position: sticky;
  top: 1rem;
  max-height: calc(100vh - 2rem);
  overflow-y: auto;
}
</style>
