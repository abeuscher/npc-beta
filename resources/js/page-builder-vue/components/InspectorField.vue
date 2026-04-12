<script setup lang="ts">
import { computed } from 'vue'
import type { FieldDef, Widget } from '../types'
import { useEditorStore } from '../stores/editor'
import TextField from './fields/TextField.vue'
import TextareaField from './fields/TextareaField.vue'
import NumberField from './fields/NumberField.vue'
import SelectField from './fields/SelectField.vue'
import ToggleField from './fields/ToggleField.vue'
import CheckboxesField from './fields/CheckboxesField.vue'
import NoticeField from './fields/NoticeField.vue'
import RichTextField from './fields/RichTextField.vue'
import ColorPicker from './primitives/ColorPicker.vue'
import GradientPicker from './primitives/GradientPicker.vue'
import NinePointAlignment from './primitives/NinePointAlignment.vue'
import ImageUploadField from './fields/ImageUploadField.vue'
import ButtonListField from './fields/ButtonListField.vue'

const props = defineProps<{
  field: FieldDef
  widget: Widget
}>()

const store = useEditorStore()

const fieldValue = computed(() => {
  const val = props.widget.config[props.field.key]
  return val !== undefined ? val : props.field.default
})

function handleUpdate(value: any) {
  store.updateLocalConfig(props.widget.id, props.field.key, value)
}

// Conditional visibility — `shown_when`/`hidden_when` are config keys; the
// field is visible/hidden when that key is truthy in the widget's config.
const isVisible = computed(() => {
  const config = props.widget.config

  if (typeof props.field.hidden_when === 'string' && config[props.field.hidden_when]) {
    return false
  }

  if (typeof props.field.shown_when === 'string' && ! config[props.field.shown_when]) {
    return false
  }

  return true
})

const componentMap: Record<string, any> = {
  text: TextField,
  url: TextField,
  textarea: TextareaField,
  number: NumberField,
  select: SelectField,
  toggle: ToggleField,
  checkboxes: CheckboxesField,
  notice: NoticeField,
  richtext: RichTextField,
  image: ImageUploadField,
  video: ImageUploadField,
  buttons: ButtonListField,
}

const fieldComponent = computed(() => componentMap[props.field.type] ?? null)
</script>

<template>
  <div v-show="isVisible" class="inspector-field">
    <template v-if="field.type === 'notice'">
      <NoticeField :field="field" />
    </template>

    <template v-else-if="field.type === 'heading'">
      <p class="inspector-field__heading">{{ field.label }}</p>
    </template>

    <template v-else-if="field.type === 'color'">
      <label class="inspector-label">{{ field.label }}</label>
      <ColorPicker
        :model-value="fieldValue"
        compact
        @update:model-value="handleUpdate"
      />
    </template>

    <template v-else-if="field.type === 'gradient'">
      <GradientPicker
        :model-value="fieldValue"
        :label="field.label"
        @update:model-value="handleUpdate"
      />
    </template>

    <template v-else-if="field.type === 'alignment'">
      <NinePointAlignment
        :model-value="fieldValue ?? 'center'"
        :label="field.label"
        @update:model-value="handleUpdate"
      />
    </template>

    <template v-else-if="fieldComponent">
      <label class="inspector-label">{{ field.label }}</label>
      <component
        :is="fieldComponent"
        :field="field"
        :widget="widget"
        :model-value="fieldValue"
        @update:model-value="handleUpdate"
      />
    </template>

    <template v-else>
      <label class="inspector-label">{{ field.label }}</label>
      <p class="inspector-hint inspector-hint--italic">Unsupported field type: {{ field.type }}</p>
    </template>
  </div>
</template>

<style scoped>
.inspector-field__heading {
  margin: 0.75rem 0 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #1f2937;
}
</style>

