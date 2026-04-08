<script setup lang="ts">
import { ref } from 'vue'
import { useEditorStore } from '../stores/editor'

const store = useEditorStore()
const editLabel = ref('')
const editingId = ref<string | null>(null)

function startEditLabel(id: string, currentLabel: string) {
  editingId.value = id
  editLabel.value = currentLabel
}

async function saveLabel() {
  if (!editingId.value) return

  await store.updateWidget(editingId.value, { label: editLabel.value })
  editingId.value = null
  editLabel.value = ''
}

function cancelEdit() {
  editingId.value = null
  editLabel.value = ''
}
</script>

<template>
  <div class="poc-blocks">
    <div
      v-for="widget in store.rootWidgets"
      :key="widget.id"
      class="poc-block"
      :class="{ 'poc-block--selected': store.selectedBlockId === widget.id }"
      @click="store.selectBlock(widget.id)"
    >
      <div class="poc-block__header">
        <span class="poc-block__type">{{ widget.widget_type_label }}</span>
        <span class="poc-block__label">
          <template v-if="editingId === widget.id">
            <input
              v-model="editLabel"
              class="poc-block__input"
              @click.stop
              @keyup.enter="saveLabel"
              @keyup.escape="cancelEdit"
            />
            <button class="poc-block__btn" @click.stop="saveLabel">Save</button>
            <button class="poc-block__btn poc-block__btn--cancel" @click.stop="cancelEdit">Cancel</button>
          </template>
          <template v-else>
            {{ widget.label }}
            <button
              class="poc-block__btn poc-block__btn--edit"
              @click.stop="startEditLabel(widget.id, widget.label)"
            >Edit</button>
          </template>
        </span>
      </div>
    </div>

    <div v-if="store.rootWidgets.length === 0" class="poc-empty">
      No widgets loaded.
    </div>

    <div v-if="store.selectedWidget" class="poc-inspector">
      <h4 class="poc-inspector__title">
        Selected: {{ store.selectedWidget.label }}
        <span class="poc-inspector__type">({{ store.selectedWidget.widget_type_handle }})</span>
      </h4>
      <pre class="poc-inspector__json">{{ JSON.stringify(store.selectedWidget.config, null, 2) }}</pre>
    </div>
  </div>
</template>

<style scoped>
.poc-blocks {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.poc-block {
  padding: 0.625rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  background: #fff;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.poc-block:hover {
  border-color: #a5b4fc;
}

.poc-block--selected {
  border-color: #6366f1;
  box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25);
}

.poc-block__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.poc-block__type {
  font-size: 0.75rem;
  color: #6366f1;
  font-weight: 600;
  flex-shrink: 0;
}

.poc-block__label {
  font-size: 0.875rem;
  color: #374151;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.poc-block__input {
  font-size: 0.875rem;
  padding: 0.125rem 0.375rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
}

.poc-block__btn {
  font-size: 0.75rem;
  padding: 0.125rem 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  background: #fff;
  cursor: pointer;
  color: #6366f1;
}

.poc-block__btn--cancel {
  color: #6b7280;
}

.poc-block__btn--edit {
  color: #9ca3af;
  border-color: transparent;
}

.poc-block__btn--edit:hover {
  color: #6366f1;
}

.poc-empty {
  text-align: center;
  color: #9ca3af;
  padding: 1rem;
  font-size: 0.875rem;
}

.poc-inspector {
  margin-top: 1rem;
  padding: 0.75rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.375rem;
  background: #fafafa;
}

.poc-inspector__title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
  margin: 0 0 0.5rem;
}

.poc-inspector__type {
  font-weight: 400;
  color: #9ca3af;
}

.poc-inspector__json {
  font-size: 0.75rem;
  color: #6b7280;
  background: #f3f4f6;
  padding: 0.5rem;
  border-radius: 0.25rem;
  overflow-x: auto;
  max-height: 200px;
  margin: 0;
}
</style>
