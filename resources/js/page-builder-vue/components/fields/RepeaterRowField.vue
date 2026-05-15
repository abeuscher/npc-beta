<script setup lang="ts">
import { computed } from 'vue'
import type { FieldDef, Widget } from '../../types'
import TextField from './TextField.vue'
import TextareaField from './TextareaField.vue'
import RichTextField from './RichTextField.vue'
import ToggleField from './ToggleField.vue'
import ButtonListField from './ButtonListField.vue'
// Self-import: a repeater nested inside a row delegates back to the parent
// component. Vue resolves this lazily through `defineAsyncComponent`.
import { defineAsyncComponent } from 'vue'
const RepeaterField = defineAsyncComponent(() => import('./RepeaterField.vue'))

const props = defineProps<{
  field: FieldDef
  widget: Widget
  modelValue: any
}>()

const emit = defineEmits<{
  'update:modelValue': [value: any]
}>()

function handleUpdate(value: any) {
  emit('update:modelValue', value)
}

const componentMap: Record<string, any> = {
  text: TextField,
  url: TextField,
  textarea: TextareaField,
  richtext: RichTextField,
  toggle: ToggleField,
  buttons: ButtonListField,
  repeater: RepeaterField,
}

const fieldComponent = computed(() => componentMap[props.field.type] ?? null)
</script>

<template>
  <div class="repeater-row-field">
    <label v-if="field.label" class="inspector-label">{{ field.label }}</label>
    <component
      :is="fieldComponent"
      v-if="fieldComponent"
      :field="field"
      :widget="widget"
      :model-value="modelValue"
      @update:model-value="handleUpdate"
    />
    <p v-else class="inspector-hint inspector-hint--italic">
      Unsupported field type: {{ field.type }}
    </p>
    <p v-if="field.helper" class="inspector-hint">{{ field.helper }}</p>
  </div>
</template>

<style scoped>
.repeater-row-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
</style>
