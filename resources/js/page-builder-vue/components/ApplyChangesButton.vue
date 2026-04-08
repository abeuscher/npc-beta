<script setup lang="ts">
import { computed } from 'vue'
import { useEditorStore } from '../stores/editor'

const store = useEditorStore()

const disabled = computed(() => {
  if (!store.selectedBlockId) return true
  return !store.isWidgetDirty(store.selectedBlockId)
})

function apply() {
  if (store.selectedBlockId) {
    store.refreshPreview(store.selectedBlockId)
  }
}
</script>

<template>
  <div class="apply-changes">
    <button
      type="button"
      class="apply-changes__btn"
      :class="{ 'apply-changes__btn--disabled': disabled }"
      :disabled="disabled"
      @click="apply"
    >
      Apply Changes
    </button>
  </div>
</template>

<style scoped>
.apply-changes {
  position: sticky;
  bottom: 0;
  z-index: 10;
  border-top: 1px solid #e5e7eb;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(4px);
  padding: 0.5rem 1rem;
}

.apply-changes__btn {
  width: 100%;
  border: none;
  border-radius: 0.5rem;
  background: var(--c-primary-600, #4f46e5);
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #fff;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  transition: background-color 0.15s;
}

.apply-changes__btn:hover:not(:disabled) {
  background: var(--c-primary-500, #6366f1);
}

.apply-changes__btn--disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
