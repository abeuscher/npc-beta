<script setup lang="ts">
import type { FieldDef } from '../../types'

defineProps<{
  field: FieldDef
  modelValue: any
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()
</script>

<template>
  <label class="inspector-toggle">
    <input
      type="checkbox"
      :checked="!!modelValue"
      class="inspector-toggle__input"
      @change="emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
    >
    <span class="inspector-toggle__label">Enabled</span>
  </label>
</template>

<style scoped>
.inspector-toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.inspector-toggle__input {
  border-radius: 0.25rem;
  border: 1px solid #d1d5db;
  color: var(--c-primary-600, #4f46e5);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.inspector-toggle__label {
  font-size: 0.875rem;
  color: #374151;
}
</style>
