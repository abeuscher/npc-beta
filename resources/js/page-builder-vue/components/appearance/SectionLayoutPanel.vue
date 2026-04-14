<script setup lang="ts">
import { computed } from 'vue'
import type { Widget } from '../../types'
import { useEditorStore } from '../../stores/editor'
import SpacingInput, { type SpacingValue } from '../primitives/SpacingInput.vue'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()

const fullWidth = computed(() => !!props.widget.appearance_config?.layout?.full_width)
const isColumnChild = computed(() => props.widget.layout_id !== null)

const padding = computed(() => props.widget.appearance_config?.layout?.padding ?? {})
const margin  = computed(() => props.widget.appearance_config?.layout?.margin ?? {})

function updateAppearance(path: string, value: any) {
  store.updateLocalAppearanceConfig(props.widget.id, path, value)
}

function applySpacing(box: 'padding' | 'margin', value: SpacingValue) {
  for (const side of ['top', 'right', 'bottom', 'left'] as const) {
    const v = value[side]
    updateAppearance(`layout.${box}.${side}`, v === null ? '' : v)
  }
}
</script>

<template>
  <div class="layout-panel">
    <p class="layout-panel__heading">Section Layout</p>

    <div class="layout-panel__section">
      <label
        class="layout-panel__toggle"
        :class="{ 'layout-panel__toggle--disabled': isColumnChild }"
        :title="isColumnChild ? 'The parent column controls width for column widgets' : undefined"
      >
        <input
          type="checkbox"
          :checked="fullWidth"
          :disabled="isColumnChild"
          class="inspector-checkbox"
          @change="updateAppearance('layout.full_width', ($event.target as HTMLInputElement).checked)"
        >
        <span>Full width</span>
      </label>
    </div>

    <div class="layout-panel__section">
      <SpacingInput
        label="Padding"
        unit="px"
        :model-value="padding"
        @update:model-value="applySpacing('padding', $event)"
      />
    </div>

    <div class="layout-panel__section">
      <SpacingInput
        label="Margin"
        unit="px"
        :model-value="margin"
        @update:model-value="applySpacing('margin', $event)"
      />
    </div>
  </div>
</template>

<style scoped>
.layout-panel {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding-top: 0.75rem;
}

.layout-panel__heading {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #1f2937;
}

.layout-panel__section {
  display: flex;
  flex-direction: column;
}

.layout-panel__toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.875rem;
  color: #374151;
}

.layout-panel__toggle--disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
