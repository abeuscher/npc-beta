<script setup lang="ts">
import { computed } from 'vue'
import type { FieldDef } from '../../types'

const props = defineProps<{
  field: FieldDef
  modelValue: any
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string[]]
}>()

const selected = computed<string[]>(() =>
  Array.isArray(props.modelValue) ? props.modelValue : []
)

const columns = computed(() => props.field.columns ?? 2)

function toggle(value: string) {
  const current = [...selected.value]
  const idx = current.indexOf(value)
  if (idx >= 0) {
    current.splice(idx, 1)
  } else {
    current.push(value)
  }
  emit('update:modelValue', current)
}
</script>

<template>
  <div
    class="inspector-checkboxes"
    :style="{ gridTemplateColumns: `repeat(${columns}, 1fr)` }"
  >
    <label
      v-for="(label, value) in (field.options ?? {})"
      :key="value"
      class="inspector-checkboxes__item"
    >
      <input
        type="checkbox"
        :checked="selected.includes(String(value))"
        class="inspector-checkbox"
        @change="toggle(String(value))"
      >
      <span class="inspector-toggle-label">{{ label }}</span>
    </label>
  </div>
</template>

<style scoped>
.inspector-checkboxes {
  display: grid;
  gap: 0.25rem 1rem;
}

.inspector-checkboxes__item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

</style>
