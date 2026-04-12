<script setup lang="ts">
import type { FieldDef } from '../../types'

const props = defineProps<{
  field: FieldDef
  modelValue: any
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number | null]
}>()

function handleInput(e: Event) {
  const val = (e.target as HTMLInputElement).value
  emit('update:modelValue', val === '' ? null : Number(val))
}
</script>

<template>
  <input
    type="number"
    :value="modelValue ?? ''"
    :min="field.min"
    class="inspector-control"
    @input="handleInput"
  >
</template>

