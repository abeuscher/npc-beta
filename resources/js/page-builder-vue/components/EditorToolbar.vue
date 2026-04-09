<script setup lang="ts">
import { useEditorStore } from '../stores/editor'

const store = useEditorStore()

function openSaveTemplateModal() {
  window.dispatchEvent(
    new CustomEvent('open-save-template-modal', {
      detail: { pageId: store.pageId },
    })
  )
}
</script>

<template>
  <div class="editor-toolbar">
    <div class="editor-toolbar__left">
      <p class="editor-toolbar__count">
        {{ store.rootWidgets.length }} block(s) on this page.
      </p>
    </div>

    <div class="editor-toolbar__right">
      <button
        v-if="store.rootWidgets.length > 0"
        type="button"
        class="editor-toolbar__btn editor-toolbar__btn--secondary"
        @click="openSaveTemplateModal"
      >
        Save as Template
      </button>
    </div>
  </div>
</template>

<style scoped>
.editor-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
  border:none;
}

.editor-toolbar__left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.editor-toolbar__count {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
}

.editor-toolbar__right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.editor-toolbar__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  border-radius: 0.5rem;
  border: none;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.editor-toolbar__btn--secondary {
  background: #fff;
  color: #374151;
  border: 1px solid #d1d5db;
}

.editor-toolbar__btn--secondary:hover {
  background-color: #f9fafb;
}

.editor-toolbar__btn--primary {
  background-color: var(--c-primary-600, #4f46e5);
  color: #fff;
  font-weight: 600;
}

.editor-toolbar__btn--primary:hover {
  background-color: var(--c-primary-500, #6366f1);
}
</style>
