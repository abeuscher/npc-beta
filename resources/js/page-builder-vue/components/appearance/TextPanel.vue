<script setup lang="ts">
import { computed } from 'vue'
import type { Widget } from '../../types'
import { useEditorStore } from '../../stores/editor'
import ColorPicker from '../primitives/ColorPicker.vue'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()

const textColor = computed(() => props.widget.appearance_config?.text?.color ?? '')
const textShadow = computed(() => props.widget.appearance_config?.text?.shadow ?? false)

function updateAppearance(path: string, value: any) {
  store.updateLocalAppearanceConfig(props.widget.id, path, value)
}
</script>

<template>
  <div class="text-panel">
    <p class="text-panel__heading">Text</p>

    <div class="text-panel__section">
      <ColorPicker
        :model-value="textColor"
        label="Color"
        @update:model-value="updateAppearance('text.color', $event)"
      >
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M5.635 21L11.29 4.2h1.42L18.365 21h-1.56l-1.74-5.1H8.935L7.195 21H5.635Zm3.72-6.48h5.29L12.38 7.14h-.76L9.355 14.52Z"/>
          </svg>
        </template>
      </ColorPicker>
      <p class="inspector-hint">Inline color styling inside rich text fields overrides this value.</p>
    </div>

    <div class="text-panel__section">
      <label class="text-panel__toggle">
        <input
          type="checkbox"
          :checked="textShadow"
          class="inspector-checkbox"
          @change="updateAppearance('text.shadow', ($event.target as HTMLInputElement).checked)"
        >
        <span>Drop Shadow</span>
      </label>
    </div>
  </div>
</template>

<style scoped>
.text-panel {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding-top: 0.75rem;
  padding-bottom: 0.75rem;
  border-top: 1px solid #e5e7eb;
  border-bottom: 1px solid #e5e7eb;
}

.text-panel__heading {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #1f2937;
}

.text-panel__section {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.text-panel__toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.875rem;
  color: #374151;
}
</style>
