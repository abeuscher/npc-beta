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
      class="inspector-checkbox"
      @change="emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
    >
    <span class="inspector-toggle-label">Enabled</span>
  </label>
</template>

<style scoped>
.inspector-toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}
</style>
