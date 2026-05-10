<script setup lang="ts">
import { computed } from 'vue'
import SpacingInput, { type SpacingValue } from '../primitives/SpacingInput.vue'

const props = withDefaults(
  defineProps<{
    config: Record<string, any>
    showFullWidth?: boolean
    fullWidthDisabled?: boolean
    fullWidthDisabledReason?: string | null
  }>(),
  {
    showFullWidth: true,
    fullWidthDisabled: false,
    fullWidthDisabledReason: null,
  },
)

const emit = defineEmits<{
  update: [path: string, value: any]
}>()

const contentFullWidth = computed(() => !!props.config?.layout?.content_full_width)
const backgroundFullWidth = computed(() => !!props.config?.layout?.background_full_width)
const padding = computed(() => props.config?.layout?.padding ?? {})
const margin  = computed(() => props.config?.layout?.margin ?? {})

const backgroundDisabled = computed(() => props.fullWidthDisabled || contentFullWidth.value)
const backgroundDisabledReason = computed(() => {
  if (props.fullWidthDisabled) return props.fullWidthDisabledReason ?? undefined
  if (contentFullWidth.value) return 'Background fills page width automatically when content is set to fill page width.'
  return undefined
})

function update(path: string, value: any) {
  emit('update', path, value)
}

function applySpacing(box: 'padding' | 'margin', value: SpacingValue) {
  for (const side of ['top', 'right', 'bottom', 'left'] as const) {
    update(`${box}.${side}`, value[side])
  }
}
</script>

<template>
  <div class="layout-panel">
    <p class="layout-panel__heading">Section Layout</p>

    <div v-if="showFullWidth" class="layout-panel__section">
      <label
        class="layout-panel__toggle"
        :class="{ 'layout-panel__toggle--disabled': fullWidthDisabled }"
        :title="fullWidthDisabled ? (fullWidthDisabledReason ?? undefined) : undefined"
      >
        <input
          type="checkbox"
          :checked="contentFullWidth"
          :disabled="fullWidthDisabled"
          class="inspector-checkbox"
          @change="update('content_full_width', ($event.target as HTMLInputElement).checked)"
        >
        <span>Content fills page width</span>
      </label>
      <label
        class="layout-panel__toggle"
        :class="{ 'layout-panel__toggle--disabled': backgroundDisabled }"
        :title="backgroundDisabledReason"
      >
        <input
          type="checkbox"
          :checked="backgroundFullWidth || contentFullWidth"
          :disabled="backgroundDisabled"
          class="inspector-checkbox"
          @change="update('background_full_width', ($event.target as HTMLInputElement).checked)"
        >
        <span>Background fills page width</span>
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
  gap: 0.375rem;
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
