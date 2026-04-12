<script setup lang="ts">
import type { FieldDef } from '../../types'

defineProps<{
  field: FieldDef
  modelValue: any
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <input
    :type="field.type === 'url' ? 'url' : 'text'"
    :value="modelValue ?? ''"
    class="inspector-control"
    @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
  >
</template>

