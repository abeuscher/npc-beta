<script setup lang="ts">
import { computed } from 'vue'
import type { FieldDef, Widget } from '../../types'
import RepeaterRowField from './RepeaterRowField.vue'

const props = defineProps<{
  field: FieldDef
  widget: Widget
  modelValue: any
}>()

const emit = defineEmits<{
  'update:modelValue': [value: any[]]
}>()

const rows = computed<any[]>(() =>
  Array.isArray(props.modelValue) ? props.modelValue : []
)

const itemLabel = computed<string>(() => props.field.item_label || 'Item')
const nestedFields = computed<FieldDef[]>(() =>
  Array.isArray(props.field.fields) ? props.field.fields : []
)

// Sub-fields render in the inspector unless explicitly marked inspector:false
// (mirrors InspectorPanel's top-level convention). The unfiltered nestedFields
// list still drives defaultRow(), so a hidden sub-field keeps its seeded /
// inline-edited value — it is only hidden from this inspector control, never
// dropped from the data.
const visibleFields = computed<FieldDef[]>(() =>
  nestedFields.value.filter((f) => f.inspector !== false)
)

function defaultRow(): Record<string, any> {
  const out: Record<string, any> = {}
  for (const f of nestedFields.value) {
    if (f.type === 'toggle') {
      out[f.key] = f.default ?? false
    } else if (f.type === 'buttons' || f.type === 'repeater') {
      out[f.key] = []
    } else {
      out[f.key] = f.default ?? ''
    }
  }
  return out
}

function update(newRows: any[]) {
  emit('update:modelValue', [...newRows])
}

function addRow() {
  update([...rows.value, defaultRow()])
}

function removeRow(index: number) {
  const list = [...rows.value]
  list.splice(index, 1)
  update(list)
}

function moveUp(index: number) {
  if (index === 0) return
  const list = [...rows.value]
  const item = list.splice(index, 1)[0]
  list.splice(index - 1, 0, item)
  update(list)
}

function moveDown(index: number) {
  if (index >= rows.value.length - 1) return
  const list = [...rows.value]
  const item = list.splice(index, 1)[0]
  list.splice(index + 1, 0, item)
  update(list)
}

function updateRow(index: number, key: string, value: any) {
  const list = rows.value.map((row, i) =>
    i === index ? { ...row, [key]: value } : row
  )
  update(list)
}
</script>

<template>
  <div class="repeater">
    <div
      v-for="(row, index) in rows"
      :key="index"
      class="repeater__item"
    >
      <div class="repeater__header">
        <span class="repeater__index">{{ itemLabel }} {{ index + 1 }}</span>
        <div class="repeater__actions">
          <button
            v-if="index > 0"
            type="button"
            class="repeater__action"
            title="Move up"
            aria-label="Move item up"
            @click="moveUp(index)"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
            </svg>
          </button>
          <button
            v-if="index < rows.length - 1"
            type="button"
            class="repeater__action"
            title="Move down"
            aria-label="Move item down"
            @click="moveDown(index)"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <button
            type="button"
            class="repeater__action repeater__action--remove"
            title="Remove"
            aria-label="Remove item"
            @click="removeRow(index)"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="repeater__fields">
        <RepeaterRowField
          v-for="nested in visibleFields"
          :key="nested.key"
          :field="nested"
          :widget="widget"
          :model-value="row[nested.key]"
          @update:model-value="(value) => updateRow(index, nested.key, value)"
        />
      </div>
    </div>

    <button
      type="button"
      class="repeater__add"
      @click="addRow"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      Add {{ itemLabel }}
    </button>
  </div>
</template>

<style scoped>
.repeater__item {
  margin-bottom: 0.75rem;
  padding: 0.75rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  background: #f9fafb;
}

.repeater__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.repeater__index {
  font-size: 0.75rem;
  font-weight: 600;
  color: #4b5563;
}

.repeater__actions {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.repeater__action {
  padding: 0.125rem;
  border: none;
  background: none;
  color: #9ca3af;
  cursor: pointer;
  border-radius: 0.25rem;
}

.repeater__action:hover {
  color: #4b5563;
}

.repeater__action--remove:hover {
  color: #dc2626;
}

.repeater__fields {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}

.repeater__add {
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

.repeater__add:hover {
  border-color: var(--c-primary-400, #818cf8);
  color: var(--c-primary-600, #4f46e5);
}

html.dark .repeater__item                   { background: rgb(17 24 39); border-color: rgb(75 85 99); }
html.dark .repeater__index                  { color: rgb(209 213 219); }
html.dark .repeater__action                 { color: rgb(156 163 175); }
html.dark .repeater__action--remove:hover   { color: rgb(248 113 113); }
html.dark .repeater__add                    { background: rgb(31 41 55); color: rgb(165 180 252); border-color: rgb(75 85 99); }
</style>
