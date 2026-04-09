<script setup lang="ts">
import { ref, nextTick } from 'vue'
import type { Widget } from '../types'
import { useEditorStore } from '../stores/editor'
import ConfirmDeleteModal from './ConfirmDeleteModal.vue'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()
const editing = ref(false)
const draft = ref('')
const labelInput = ref<HTMLInputElement | null>(null)
const showDeleteModal = ref(false)

function startEditing() {
  draft.value = props.widget.label
  editing.value = true
  nextTick(() => labelInput.value?.focus())
}

function saveLabel() {
  editing.value = false
  if (draft.value !== props.widget.label) {
    store.updateLocalConfig(props.widget.id, null, null, draft.value)
  }
}

function cancelEditing() {
  editing.value = false
}

async function confirmDelete() {
  showDeleteModal.value = false
  await store.deleteWidget(props.widget.id)
}
</script>

<template>
  <div class="inspector-header">
    <p class="inspector-header__type-badge">
      {{ widget.widget_type_label }}
    </p>

    <div v-if="!editing" class="inspector-header__display">
      <span class="inspector-header__label">{{ widget.label }}</span>
      <button
        type="button"
        title="Rename block"
        class="inspector-header__edit-btn"
        @click="startEditing"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="inspector-header__icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.364-6.364a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.414.586H9v-2.414a2 2 0 01.586-1.414z"/>
        </svg>
      </button>
      <button
        v-if="!widget.is_required"
        type="button"
        title="Delete block"
        class="inspector-header__delete-btn"
        @click="showDeleteModal = true"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="inspector-header__icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
      </button>
    </div>

    <div v-else class="inspector-header__edit-row">
      <input
        ref="labelInput"
        v-model="draft"
        type="text"
        class="inspector-header__input"
        @keydown.enter.prevent="saveLabel"
        @keydown.escape.prevent="cancelEditing"
      >
      <button
        type="button"
        class="inspector-header__ok-btn"
        @click="saveLabel"
      >OK</button>
      <button
        type="button"
        class="inspector-header__cancel-btn"
        @click="cancelEditing"
      >Cancel</button>
    </div>

    <ConfirmDeleteModal
      :visible="showDeleteModal"
      :widget-label="widget.label"
      @confirm="confirmDelete"
      @cancel="showDeleteModal = false"
    />
  </div>
</template>

<style scoped>
.inspector-header {
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem 0.5rem 0 0;
  background: #fff;
  padding: 0.75rem 1rem;
}

.inspector-header__type-badge {
  margin: 0 0 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
}

.inspector-header__display {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.inspector-header__label {
  flex: 1;
  font-size: 0.875rem;
  font-weight: 500;
  color: #1f2937;
}

.inspector-header__edit-btn {
  flex-shrink: 0;
  padding: 0.25rem;
  border-radius: 0.25rem;
  border: none;
  background: none;
  color: #1f2937;
  cursor: pointer;
}

.inspector-header__edit-btn:hover {
  background: #f3f4f6;
  color: #111827;
}

.inspector-header__delete-btn {
  flex-shrink: 0;
  padding: 0.25rem;
  border-radius: 0.25rem;
  border: none;
  background: none;
  color: #dc2626;
  cursor: pointer;
}

.inspector-header__delete-btn:hover {
  background: #fef2f2;
  color: #b91c1c;
}

@media (prefers-color-scheme: dark) {
  .inspector-header__edit-btn {
    color: #fff;
  }

  .inspector-header__edit-btn:hover {
    background: #374151;
    color: #fff;
  }

  .inspector-header__delete-btn {
    color: #f87171;
  }

  .inspector-header__delete-btn:hover {
    background: #451a1a;
    color: #fca5a5;
  }
}

.inspector-header__icon {
  width: 0.875rem;
  height: 0.875rem;
}

.inspector-header__edit-row {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.inspector-header__input {
  flex: 1;
  min-width: 0;
  border: 1px solid var(--c-primary-400, #818cf8);
  border-radius: 0.25rem;
  padding: 0.125rem 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #1f2937;
  background: #fff;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.inspector-header__input:focus {
  outline: none;
}

.inspector-header__ok-btn {
  flex-shrink: 0;
  border: none;
  border-radius: 0.25rem;
  background: var(--c-primary-600, #4f46e5);
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #fff;
  cursor: pointer;
}

.inspector-header__ok-btn:hover {
  background: var(--c-primary-500, #6366f1);
}

.inspector-header__cancel-btn {
  flex-shrink: 0;
  border: none;
  border-radius: 0.25rem;
  background: none;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  color: #6b7280;
  cursor: pointer;
}

.inspector-header__cancel-btn:hover {
  background: #f3f4f6;
}
</style>
