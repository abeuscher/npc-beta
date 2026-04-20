<script setup lang="ts">
import { computed } from 'vue'
import type { FieldDef, Widget } from '../../types'

const props = defineProps<{
  field: FieldDef
  widget: Widget
  modelValue: any
}>()

const emit = defineEmits<{
  'update:modelValue': [value: any]
}>()

const buttons = computed(() =>
  Array.isArray(props.modelValue) ? props.modelValue : []
)

const styleOptions = computed(() =>
  props.field.style_options ?? { primary: 'Primary', secondary: 'Secondary', text: 'Text Only' }
)

function update(newList: any[]) {
  emit('update:modelValue', [...newList])
}

function addButton() {
  update([...buttons.value, { text: '', url: '', style: 'primary' }])
}

function removeButton(index: number) {
  const list = [...buttons.value]
  list.splice(index, 1)
  update(list)
}

function moveUp(index: number) {
  if (index === 0) return
  const list = [...buttons.value]
  const item = list.splice(index, 1)[0]
  list.splice(index - 1, 0, item)
  update(list)
}

function moveDown(index: number) {
  if (index >= buttons.value.length - 1) return
  const list = [...buttons.value]
  const item = list.splice(index, 1)[0]
  list.splice(index + 1, 0, item)
  update(list)
}

function updateField(index: number, key: string, value: string) {
  const list = buttons.value.map((btn, i) =>
    i === index ? { ...btn, [key]: value } : btn
  )
  update(list)
}
</script>

<template>
  <div class="button-list">
    <div
      v-for="(btn, index) in buttons"
      :key="index"
      class="button-list__item"
    >
      <div class="button-list__header">
        <span class="button-list__index">Button {{ index + 1 }}</span>
        <div class="button-list__actions">
          <button
            v-if="index > 0"
            type="button"
            class="button-list__action"
            title="Move up"
            @click="moveUp(index)"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
            </svg>
          </button>
          <button
            v-if="index < buttons.length - 1"
            type="button"
            class="button-list__action"
            title="Move down"
            @click="moveDown(index)"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <button
            type="button"
            class="button-list__action button-list__action--remove"
            title="Remove button"
            @click="removeButton(index)"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="button-list__fields">
        <input
          type="text"
          :value="btn.text"
          placeholder="Button text"
          class="button-list__input"
          @input="updateField(index, 'text', ($event.target as HTMLInputElement).value)"
        >
        <input
          type="text"
          :value="btn.url"
          placeholder="URL (e.g. /about or https://example.com)"
          class="button-list__input"
          @input="updateField(index, 'url', ($event.target as HTMLInputElement).value)"
        >
        <select
          :value="btn.style"
          class="button-list__input"
          @change="updateField(index, 'style', ($event.target as HTMLSelectElement).value)"
        >
          <option
            v-for="(label, value) in styleOptions"
            :key="value"
            :value="value"
          >{{ label }}</option>
        </select>
      </div>
    </div>

    <button
      type="button"
      class="button-list__add"
      @click="addButton"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      Add Button
    </button>
  </div>
</template>

<style scoped>
.button-list__item {
  margin-bottom: 0.5rem;
  padding: 0.75rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  background: #f9fafb;
}

.button-list__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.button-list__index {
  font-size: 0.75rem;
  font-weight: 500;
  color: #6b7280;
}

.button-list__actions {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.button-list__action {
  padding: 0.125rem;
  border: none;
  background: none;
  color: #9ca3af;
  cursor: pointer;
  border-radius: 0.25rem;
}

.button-list__action:hover {
  color: #4b5563;
}

.button-list__action--remove:hover {
  color: #dc2626;
}

.button-list__fields {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.button-list__input {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.375rem 0.5rem;
  font-size: 0.875rem;
  color: #1f2937;
  background: #fff;
}

.button-list__input:focus {
  outline: none;
  border-color: var(--c-primary-400, #818cf8);
  box-shadow: 0 0 0 1px var(--c-primary-400, #818cf8);
}

.button-list__add {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 0.75rem;
  border: 1px dashed #d1d5db;
  border-radius: 0.5rem;
  background: none;
  font-size: 0.75rem;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
}

.button-list__add:hover {
  border-color: var(--c-primary-400, #818cf8);
  color: var(--c-primary-600, #4f46e5);
}

html.dark .button-list                         { background: rgb(31 41 55); border-color: rgb(75 85 99); }
html.dark .button-list__item                   { background: rgb(17 24 39); border-color: rgb(75 85 99); }
html.dark .button-list__header                 { color: rgb(209 213 219); }
html.dark .button-list__index                  { color: rgb(156 163 175); }
html.dark .button-list__input                  { background: rgb(17 24 39); color: rgb(229 231 235); border-color: rgb(75 85 99); }
html.dark .button-list__action                 { color: rgb(156 163 175); }
html.dark .button-list__action--remove:hover   { color: rgb(248 113 113); }
html.dark .button-list__add                    { background: rgb(31 41 55); color: rgb(165 180 252); border-color: rgb(75 85 99); }
</style>
