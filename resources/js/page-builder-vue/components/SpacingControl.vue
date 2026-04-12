<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Widget } from '../types'
import { useEditorStore } from '../stores/editor'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()
const open = ref(false)

const padding = computed(() => props.widget.appearance_config?.layout?.padding ?? {})
const margin  = computed(() => props.widget.appearance_config?.layout?.margin ?? {})

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
  <div class="spacing-control">
    <button
      type="button"
      class="spacing-control__toggle"
      @click="open = !open"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="spacing-control__chevron"
        :class="{ 'spacing-control__chevron--open': open }"
        width="14"
        height="14"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
      </svg>
      Spacing & Layout
    </button>

    <div v-show="open" class="spacing-control__body">
      <!-- Padding -->
      <div>
        <p class="spacing-control__section-label">Padding (px)</p>
        <div class="spacing-control__grid">
          <div class="spacing-control__field">
            <label class="spacing-control__field-label">All</label>
            <input
              type="number"
              min="0"
              :value="paddingAll"
              :placeholder="paddingAllPlaceholder"
              class="spacing-control__input"
              @input="setPaddingAll(($event.target as HTMLInputElement).value)"
            >
          </div>
          <div v-for="item in paddingKeys" :key="item.key" class="spacing-control__field">
            <label class="spacing-control__field-label">{{ item.label }}</label>
            <input
              type="number"
              min="0"
              :value="padding[item.key] ?? ''"
              class="spacing-control__input"
              @input="updateAppearance('layout.padding.' + item.key, ($event.target as HTMLInputElement).value)"
            >
          </div>
        </div>
      </div>

      <!-- Margin -->
      <div>
        <p class="spacing-control__section-label">Margin (px)</p>
        <div class="spacing-control__grid">
          <div class="spacing-control__field">
            <label class="spacing-control__field-label">All</label>
            <input
              type="number"
              min="0"
              :value="marginAll"
              :placeholder="marginAllPlaceholder"
              class="spacing-control__input"
              @input="setMarginAll(($event.target as HTMLInputElement).value)"
            >
          </div>
          <div v-for="item in marginKeys" :key="item.key" class="spacing-control__field">
            <label class="spacing-control__field-label">{{ item.label }}</label>
            <input
              type="number"
              min="0"
              :value="margin[item.key] ?? ''"
              class="spacing-control__input"
              @input="updateAppearance('layout.margin.' + item.key, ($event.target as HTMLInputElement).value)"
            >
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.spacing-control__toggle {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  border: none;
  background: none;
  padding: 0;
  font-size: 0.875rem;
  font-weight: 500;
  color: #4b5563;
  cursor: pointer;
}

.spacing-control__toggle:hover {
  color: #111827;
}

.spacing-control__chevron {
  transition: transform 0.15s;
}

.spacing-control__chevron--open {
  transform: rotate(90deg);
}

.spacing-control__body {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-top: 0.75rem;
}

.spacing-control__section-label {
  margin: 0 0 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
}

.spacing-control__grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.5rem;
}

.spacing-control__field-label {
  display: block;
  text-align: center;
  font-size: 0.75rem;
  color: #9ca3af;
  margin-bottom: 0.25rem;
}

.spacing-control__input {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.25rem 0.375rem;
  font-size: 0.875rem;
  text-align: center;
  color: #1f2937;
  background: #fff;
}

.spacing-control__input:focus {
  outline: none;
  border-color: var(--c-primary-400, #818cf8);
  box-shadow: 0 0 0 1px var(--c-primary-400, #818cf8);
}
</style>
