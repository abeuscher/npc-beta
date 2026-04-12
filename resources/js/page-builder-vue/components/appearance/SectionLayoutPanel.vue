<script setup lang="ts">
import { computed } from 'vue'
import type { Widget } from '../../types'
import { useEditorStore } from '../../stores/editor'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()

const fullWidth = computed(() => !!props.widget.appearance_config?.layout?.full_width)
const isColumnChild = computed(() => props.widget.layout_id !== null)

const padding = computed(() => props.widget.appearance_config?.layout?.padding ?? {})
const margin = computed(() => props.widget.appearance_config?.layout?.margin ?? {})

// Padding "All" computed
const paddingAll = computed(() => {
  const t = padding.value.top ?? ''
  const r = padding.value.right ?? ''
  const b = padding.value.bottom ?? ''
  const l = padding.value.left ?? ''
  return (t === r && r === b && b === l && t !== '') ? t : ''
})

const paddingAllPlaceholder = computed(() => {
  const t = padding.value.top ?? ''
  const r = padding.value.right ?? ''
  const b = padding.value.bottom ?? ''
  const l = padding.value.left ?? ''
  return (t === r && r === b && b === l) ? '' : 'mixed'
})

// Margin "All" computed
const marginAll = computed(() => {
  const t = margin.value.top ?? ''
  const r = margin.value.right ?? ''
  const b = margin.value.bottom ?? ''
  const l = margin.value.left ?? ''
  return (t === r && r === b && b === l && t !== '') ? t : ''
})

const marginAllPlaceholder = computed(() => {
  const t = margin.value.top ?? ''
  const r = margin.value.right ?? ''
  const b = margin.value.bottom ?? ''
  const l = margin.value.left ?? ''
  return (t === r && r === b && b === l) ? '' : 'mixed'
})

function updateAppearance(path: string, value: any) {
  store.updateLocalAppearanceConfig(props.widget.id, path, value)
}

function setPaddingAll(value: string) {
  const v = value === '' ? '' : value
  updateAppearance('layout.padding.top', v)
  updateAppearance('layout.padding.right', v)
  updateAppearance('layout.padding.bottom', v)
  updateAppearance('layout.padding.left', v)
}

function setMarginAll(value: string) {
  const v = value === '' ? '' : value
  updateAppearance('layout.margin.top', v)
  updateAppearance('layout.margin.right', v)
  updateAppearance('layout.margin.bottom', v)
  updateAppearance('layout.margin.left', v)
}

const paddingKeys = [
  { key: 'left', label: 'Left' },
  { key: 'top', label: 'Top' },
  { key: 'right', label: 'Right' },
  { key: 'bottom', label: 'Bottom' },
]

const marginKeys = [
  { key: 'left', label: 'Left' },
  { key: 'top', label: 'Top' },
  { key: 'right', label: 'Right' },
  { key: 'bottom', label: 'Bottom' },
]
</script>

<template>
  <div class="layout-panel">
    <p class="layout-panel__heading">Section Layout</p>

    <!-- Width -->
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

    <!-- Padding -->
    <div class="layout-panel__section">
      <p class="inspector-section-title layout-panel__section-label">Padding (px)</p>
      <div class="layout-panel__grid">
        <div class="layout-panel__field">
          <label class="inspector-label layout-panel__field-label">All</label>
          <input
            type="number"
            min="0"
            :value="paddingAll"
            :placeholder="paddingAllPlaceholder"
            class="inspector-control layout-panel__input"
            @input="setPaddingAll(($event.target as HTMLInputElement).value)"
          >
        </div>
        <div v-for="item in paddingKeys" :key="item.key" class="layout-panel__field">
          <label class="inspector-label layout-panel__field-label">{{ item.label }}</label>
          <input
            type="number"
            min="0"
            :value="padding[item.key] ?? ''"
            class="inspector-control layout-panel__input"
            @input="updateAppearance('layout.padding.' + item.key, ($event.target as HTMLInputElement).value)"
          >
        </div>
      </div>
    </div>

    <!-- Margin -->
    <div class="layout-panel__section">
      <p class="inspector-section-title layout-panel__section-label">Margin (px)</p>
      <div class="layout-panel__grid">
        <div class="layout-panel__field">
          <label class="inspector-label layout-panel__field-label">All</label>
          <input
            type="number"
            min="0"
            :value="marginAll"
            :placeholder="marginAllPlaceholder"
            class="inspector-control layout-panel__input"
            @input="setMarginAll(($event.target as HTMLInputElement).value)"
          >
        </div>
        <div v-for="item in marginKeys" :key="item.key" class="layout-panel__field">
          <label class="inspector-label layout-panel__field-label">{{ item.label }}</label>
          <input
            type="number"
            min="0"
            :value="margin[item.key] ?? ''"
            class="inspector-control layout-panel__input"
            @input="updateAppearance('layout.margin.' + item.key, ($event.target as HTMLInputElement).value)"
          >
        </div>
      </div>
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

.layout-panel__section-label {
  margin-bottom: 0.5rem;
}

.layout-panel__grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.5rem;
}

.layout-panel__field-label {
  text-align: center;
  color: #9ca3af;
}

.layout-panel__input {
  padding: 0.25rem 0.375rem;
  text-align: center;
}
</style>
