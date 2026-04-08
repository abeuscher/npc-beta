<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { configure } from './api'
import type { BootstrapData } from './types'
import { useEditorStore } from './stores/editor'
import EditorToolbar from './components/EditorToolbar.vue'
import PreviewCanvas from './components/PreviewCanvas.vue'
import BlockListPoc from './components/BlockListPoc.vue'

const store = useEditorStore()

function handleTreeUpdated() {
  store.reloadTree()
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
  window.addEventListener('template-saved', handleTemplateSaved)
})

onUnmounted(() => {
  window.removeEventListener('widget-tree-updated', handleTreeUpdated)
  window.removeEventListener('template-saved', handleTemplateSaved)
})
</script>

<template>
  <div class="vue-editor">
    <EditorToolbar />

    <PreviewCanvas v-if="store.editorMode === 'edit'" />
    <BlockListPoc v-if="store.editorMode === 'handles'" />
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
</style>
