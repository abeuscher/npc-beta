<script setup lang="ts">
import { computed } from 'vue'
import type { Widget } from '../types'
import { useEditorStore } from '../stores/editor'
import ColorPicker from './primitives/ColorPicker.vue'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()

// Check which keys the widget already defines in its config_schema
const schemaKeys = computed(() => {
  const keys = new Set<string>()
  for (const f of props.widget.widget_type_config_schema ?? []) {
    if (f.key) keys.add(f.key)
  }
  return keys
})

const showFullWidth = computed(() => !schemaKeys.value.has('full_width'))
const showBgColor = computed(() => !schemaKeys.value.has('background_color'))
const showTextColor = computed(() => !schemaKeys.value.has('text_color'))

const fullWidth = computed(() => !!props.widget.appearance_config?.layout?.full_width)
const backgroundColor = computed(() => props.widget.appearance_config?.background?.color ?? '#ffffff')
const textColor = computed(() => props.widget.appearance_config?.text?.color ?? '#000000')

function updateAppearance(path: string, value: any) {
  store.updateLocalAppearanceConfig(props.widget.id, path, value)
}

const bgField = { key: 'background_color', label: 'Background Color', type: 'color', helper: '#ffffff' }
const textField = { key: 'text_color', label: 'Text Color', type: 'color', helper: '#000000' }
</script>

<template>
  <div v-if="showFullWidth || showBgColor || showTextColor" class="appearance-controls">
    <label v-if="showFullWidth" class="appearance-controls__toggle">
      <input
        type="checkbox"
        :checked="fullWidth"
        class="inspector-checkbox"
        @change="updateAppearance('layout.full_width', ($event.target as HTMLInputElement).checked)"
      >
      <span>Full width</span>
    </label>

    <div v-if="showBgColor" class="appearance-controls__field">
      <ColorPicker
        :model-value="backgroundColor"
        label="Background Color"
        :placeholder="bgField.helper"
        @update:model-value="updateAppearance('background.color', $event)"
      />
    </div>

    <div v-if="showTextColor" class="appearance-controls__field">
      <ColorPicker
        :model-value="textColor"
        label="Text Color"
        :placeholder="textField.helper"
        @update:model-value="updateAppearance('text.color', $event)"
      />
    </div>
  </div>
</template>

<style scoped>
.appearance-controls {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding-bottom: 0.75rem;
  margin-bottom: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
}

.appearance-controls__toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.875rem;
  color: #374151;
}

.appearance-controls__field {
  display: flex;
  flex-direction: column;
}
</style>
