<script setup lang="ts">
import { computed, onMounted } from 'vue'
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

const defaults: Record<string, any> = {
  background_color: '#ffffff',
  text_color: '#000000',
}

// Populate defaults on mount for widgets with empty style_config values
// Only for controls this widget doesn't define in its own config_schema
onMounted(() => {
  for (const [key, defaultValue] of Object.entries(defaults)) {
    if (!schemaKeys.value.has(key) && (props.widget.style_config?.[key] === undefined || props.widget.style_config?.[key] === null || props.widget.style_config?.[key] === '')) {
      store.updateLocalStyleConfig(props.widget.id, key, defaultValue)
    }
  }
})

const fullWidth = computed(() => !!props.widget.style_config?.full_width)
const backgroundColor = computed(() => props.widget.style_config?.background_color ?? '#ffffff')
const textColor = computed(() => props.widget.style_config?.text_color ?? '#000000')

function updateStyle(key: string, value: any) {
  store.updateLocalStyleConfig(props.widget.id, key, value)
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
        class="appearance-controls__checkbox"
        @change="updateStyle('full_width', ($event.target as HTMLInputElement).checked)"
      >
      <span>Full width</span>
    </label>

    <div v-if="showBgColor" class="appearance-controls__field">
      <ColorPicker
        :model-value="backgroundColor"
        label="Background Color"
        :placeholder="bgField.helper"
        @update:model-value="updateStyle('background_color', $event)"
      />
    </div>

    <div v-if="showTextColor" class="appearance-controls__field">
      <ColorPicker
        :model-value="textColor"
        label="Text Color"
        :placeholder="textField.helper"
        @update:model-value="updateStyle('text_color', $event)"
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

.appearance-controls__checkbox {
  border-radius: 0.25rem;
  border: 1px solid #d1d5db;
  color: var(--c-primary-600, #4f46e5);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.appearance-controls__field {
  display: flex;
  flex-direction: column;
}

.appearance-controls__label {
  display: block;
  margin-bottom: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  color: #4b5563;
}
</style>
