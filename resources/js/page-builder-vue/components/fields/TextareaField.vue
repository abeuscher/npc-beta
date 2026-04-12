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
  <textarea
    :value="modelValue ?? ''"
    rows="4"
    class="inspector-control inspector-textarea"
    @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
  ></textarea>
</template>

<style scoped>
.inspector-textarea {
  resize: vertical;
}
</style>
