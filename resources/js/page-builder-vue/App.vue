<script setup lang="ts">
import { onMounted } from 'vue'
import type { BootstrapData } from './types'
import { configure } from './api'
import { useEditorStore } from './stores/editor'
import BlockListPoc from './components/BlockListPoc.vue'

const store = useEditorStore()

onMounted(() => {
  const el = document.getElementById('page-builder-app')
  if (!el) return

  const raw = el.getAttribute('data-bootstrap')
  if (!raw) return

  const data: BootstrapData = JSON.parse(raw)

  configure(data.csrf_token, data.api_base_url)
  store.loadTree(data)
})
</script>

<template>
  <div class="vue-poc">
    <div class="vue-poc__header">
      <span class="vue-poc__badge">Vue PoC</span>
      <span class="vue-poc__info">
        {{ store.rootWidgets.length }} block(s) loaded from API
      </span>
    </div>
    <BlockListPoc />
  </div>
</template>

<style scoped>
.vue-poc {
  margin-top: 1.5rem;
  border: 2px dashed #6366f1;
  border-radius: 0.5rem;
  padding: 1rem;
  background: #f5f3ff;
}

.vue-poc__header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.vue-poc__badge {
  display: inline-block;
  padding: 0.125rem 0.5rem;
  background: #6366f1;
  color: #fff;
  font-size: 0.75rem;
  font-weight: 600;
  border-radius: 0.25rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.vue-poc__info {
  font-size: 0.875rem;
  color: #6b7280;
}
</style>
