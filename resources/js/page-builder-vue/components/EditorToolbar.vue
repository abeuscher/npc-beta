<script setup lang="ts">
import { useEditorStore } from '../stores/editor'

const store = useEditorStore()

function openWidgetPicker() {
  window.dispatchEvent(new CustomEvent('open-widget-picker', { detail: {} }))
}

function openSaveTemplateModal() {
  window.dispatchEvent(new CustomEvent('open-save-template-modal', { detail: {} }))
}
</script>

<template>
  <div class="editor-toolbar">
    <div class="editor-toolbar__left">
      <p class="editor-toolbar__count">
        {{ store.rootWidgets.length }} block(s) on this page.
      </p>

      <div class="editor-toolbar__mode-toggle">
        <button
          type="button"
          class="editor-toolbar__mode-btn"
          :class="{ 'editor-toolbar__mode-btn--active': store.editorMode === 'edit' }"
          @click="store.setMode('edit')"
        >
          Edit
        </button>
        <button
          type="button"
          class="editor-toolbar__mode-btn"
          :class="{ 'editor-toolbar__mode-btn--active': store.editorMode === 'handles' }"
          @click="store.setMode('handles')"
        >
          Handles
        </button>
      </div>
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
      <button
        type="button"
        class="editor-toolbar__btn editor-toolbar__btn--primary"
        @click="openWidgetPicker"
      >
        + Add Block
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

.editor-toolbar__mode-toggle {
  display: inline-flex;
  border-radius: 0.5rem;
  border: 1px solid #d1d5db;
  overflow: hidden;
}

.editor-toolbar__mode-btn {
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: background-color 0.15s, color 0.15s;
  background: #fff;
  color: #374151;
}

.editor-toolbar__mode-btn:hover {
  background-color: #f9fafb;
}

.editor-toolbar__mode-btn--active {
  background-color: var(--c-primary-600, #4f46e5);
  color: #fff;
}

.editor-toolbar__mode-btn--active:hover {
  background-color: var(--c-primary-600, #4f46e5);
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
