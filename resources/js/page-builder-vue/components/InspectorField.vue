<script setup lang="ts">
import { computed, onMounted } from 'vue'
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

// Populate default value on mount if config has no value
onMounted(() => {
  if (
    props.widget.config[props.field.key] === undefined &&
    props.field.default !== undefined
  ) {
    store.updateLocalConfig(props.widget.id, props.field.key, props.field.default)
  }
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

    <template v-else-if="field.type === 'color'">
      <ColorPicker
        :model-value="fieldValue"
        :label="field.label"
        :placeholder="field.helper ?? ''"
        @update:model-value="handleUpdate"
      />
    </template>

    <template v-else-if="fieldComponent">
      <label class="inspector-field__label">{{ field.label }}</label>
      <component
        :is="fieldComponent"
        :field="field"
        :widget="widget"
        :model-value="fieldValue"
        @update:model-value="handleUpdate"
      />
    </template>

    <template v-else>
      <label class="inspector-field__label">{{ field.label }}</label>
      <p class="inspector-field__fallback">Unsupported field type: {{ field.type }}</p>
    </template>
  </div>
</template>

<style scoped>
.inspector-field__label {
  display: block;
  margin-bottom: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  color: #4b5563;
}

.inspector-field__fallback {
  margin: 0;
  font-size: 0.75rem;
  color: #9ca3af;
  font-style: italic;
}
</style>
