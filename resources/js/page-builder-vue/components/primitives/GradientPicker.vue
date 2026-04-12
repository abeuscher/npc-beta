<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import ColorPicker from './ColorPicker.vue'
import {
  composeGradientCss,
  type GradientLayer,
  type GradientValue,
} from '../../helpers/gradient'

const props = withDefaults(
  defineProps<{
    modelValue?: GradientValue | null
    label?: string
    compact?: boolean
  }>(),
  {
    modelValue: null,
    label: '',
    compact: false,
  }
)

const emit = defineEmits<{
  'update:modelValue': [value: GradientValue | null]
}>()

interface GradientPreset {
  label: string
  layer: GradientLayer
}

const PRESETS: GradientPreset[] = [
  { label: 'Lavender Sky',  layer: { type: 'linear', from: '#e0c3fc', to: '#8ec5fc', angle: 180, from_alpha: 100, to_alpha: 100 } },
  { label: 'Sunrise',       layer: { type: 'linear', from: '#ff9a9e', to: '#fad0c4', angle: 180, from_alpha: 100, to_alpha: 100 } },
  { label: 'Calm Sky',      layer: { type: 'linear', from: '#a1c4fd', to: '#c2e9fb', angle: 180, from_alpha: 100, to_alpha: 100 } },
  { label: 'Mint Meadow',   layer: { type: 'linear', from: '#d4fc79', to: '#96e6a1', angle: 180, from_alpha: 100, to_alpha: 100 } },
  { label: 'Dusk',          layer: { type: 'linear', from: '#2c3e50', to: '#4ca1af', angle: 180, from_alpha: 100, to_alpha: 100 } },
  { label: 'Sunset',        layer: { type: 'linear', from: '#ff7e5f', to: '#feb47b', angle: 180, from_alpha: 100, to_alpha: 100 } },
  { label: 'Slate',         layer: { type: 'linear', from: '#232526', to: '#414345', angle: 180, from_alpha: 100, to_alpha: 100 } },
]

const DEFAULT_LAYER: GradientLayer = {
  type: 'linear',
  from: '#ffffff',
  to: '#000000',
  angle: 180,
  from_alpha: 100,
  to_alpha: 100,
}

const rootEl = ref<HTMLElement | null>(null)
const isOpen = ref(false)

defineExpose({ isOpen })

function onDocumentClick(e: MouseEvent): void {
  if (!isOpen.value) return
  if (!rootEl.value) return
  if (rootEl.value.contains(e.target as Node)) return
  isOpen.value = false
}

function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape' && isOpen.value) {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onKeydown)
})

const gradients = computed<GradientLayer[]>(() => {
  return Array.isArray(props.modelValue?.gradients) ? props.modelValue!.gradients : []
})

const hasGradient = computed(() => gradients.value.length > 0)

const previewCss = computed(() => composeGradientCss(props.modelValue ?? null))

const triggerStyle = computed(() => {
  if (!hasGradient.value || previewCss.value === '') {
    return undefined
  }
  return { backgroundImage: previewCss.value }
})

function toggleOpen(): void {
  isOpen.value = !isOpen.value
}

function emitGradients(next: GradientLayer[]): void {
  if (next.length === 0) {
    emit('update:modelValue', null)
    return
  }
  emit('update:modelValue', { gradients: next })
}

function applyPreset(preset: GradientPreset): void {
  const next = gradients.value.length > 0 ? [...gradients.value] : [{ ...DEFAULT_LAYER }]
  next[0] = { ...preset.layer }
  emitGradients(next)
}

function updateLayer(index: number, partial: Partial<GradientLayer>): void {
  const next = gradients.value.map((g, i) => (i === index ? { ...g, ...partial } : g))
  emitGradients(next)
}

function addSecondGradient(): void {
  if (gradients.value.length !== 1) return
  emitGradients([...gradients.value, { ...DEFAULT_LAYER }])
}

function removeSecondGradient(): void {
  if (gradients.value.length < 2) return
  emitGradients([gradients.value[0]])
}

function clearAll(): void {
  emit('update:modelValue', null)
}
</script>

<template>
  <div ref="rootEl" class="gradient-picker">
    <template v-if="!compact">
      <label v-if="label" class="inspector-label">{{ label }}</label>

      <button
        type="button"
        class="gradient-picker__trigger"
        :class="{ 'gradient-picker__trigger--open': isOpen }"
        @click="toggleOpen"
      >
        <span
          class="gradient-picker__trigger-swatch"
          :class="{ 'gradient-picker__trigger-swatch--empty': !hasGradient }"
          :style="triggerStyle"
        >
          <span v-if="!hasGradient" class="gradient-picker__trigger-empty">no gradient</span>
        </span>
        <span class="gradient-picker__trigger-caret" aria-hidden="true">
          {{ isOpen ? '▴' : '▾' }}
        </span>
      </button>
    </template>

    <div v-if="isOpen" class="gradient-picker__panel">
      <div class="gradient-picker__body">
        <section class="gradient-picker__section">
          <p class="inspector-section-title">Presets</p>
          <div class="gradient-picker__presets">
            <button
              v-for="preset in PRESETS"
              :key="preset.label"
              type="button"
              class="gradient-picker__preset"
              :title="preset.label"
              :style="{
                backgroundImage: `linear-gradient(${preset.layer.angle}deg, ${preset.layer.from}, ${preset.layer.to})`,
              }"
              @click="applyPreset(preset)"
            />
            <button
              type="button"
              class="gradient-picker__preset gradient-picker__preset--none"
              title="Remove gradient"
              @click="clearAll"
            >
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <line x1="4" y1="4" x2="20" y2="20" stroke="#dc2626" stroke-width="3" stroke-linecap="round" />
              </svg>
            </button>
          </div>
        </section>

        <section
          v-for="(gradient, index) in gradients"
          :key="index"
          class="gradient-picker__section gradient-picker__section--editor"
        >
          <div class="gradient-picker__section-head">
            <p class="inspector-section-title">
              Gradient {{ index + 1 }}
            </p>
            <button
              v-if="index === 1"
              type="button"
              class="gradient-picker__remove"
              title="Remove second gradient"
              @click="removeSecondGradient"
            >&times;</button>
          </div>

          <!-- Row 1: From color + opacity -->
          <div class="gradient-picker__row">
            <div class="gradient-picker__row-color">
              <ColorPicker
                :model-value="gradient.from"
                label="From"
                @update:model-value="updateLayer(index, { from: $event })"
              />
            </div>
            <div class="gradient-picker__row-opacity">
              <label class="inspector-label">Opacity ({{ gradient.from_alpha ?? 100 }}%)</label>
              <input
                type="range"
                min="0"
                max="100"
                :value="gradient.from_alpha ?? 100"
                class="inspector-range"
                @input="updateLayer(index, { from_alpha: parseInt(($event.target as HTMLInputElement).value, 10) })"
              >
            </div>
          </div>

          <!-- Row 2: To color + opacity -->
          <div class="gradient-picker__row">
            <div class="gradient-picker__row-color">
              <ColorPicker
                :model-value="gradient.to"
                label="To"
                @update:model-value="updateLayer(index, { to: $event })"
              />
            </div>
            <div class="gradient-picker__row-opacity">
              <label class="inspector-label">Opacity ({{ gradient.to_alpha ?? 100 }}%)</label>
              <input
                type="range"
                min="0"
                max="100"
                :value="gradient.to_alpha ?? 100"
                class="inspector-range"
                @input="updateLayer(index, { to_alpha: parseInt(($event.target as HTMLInputElement).value, 10) })"
              >
            </div>
          </div>

          <!-- Row 3: Type + Angle -->
          <div class="gradient-picker__row">
            <div class="gradient-picker__row-half">
              <label class="inspector-label">Type</label>
              <select
                :value="gradient.type"
                class="inspector-control inspector-control--sm"
                @change="updateLayer(index, { type: ($event.target as HTMLSelectElement).value as GradientLayer['type'] })"
              >
                <option value="linear">Linear</option>
                <option value="radial">Radial</option>
              </select>
            </div>
            <div v-if="gradient.type === 'linear'" class="gradient-picker__row-half">
              <label class="inspector-label">Angle (deg)</label>
              <input
                type="number"
                min="0"
                max="360"
                :value="gradient.angle ?? 180"
                class="inspector-control inspector-control--sm"
                @input="updateLayer(index, { angle: parseInt(($event.target as HTMLInputElement).value, 10) || 0 })"
              >
            </div>
          </div>

          <!-- Row 4: CSS override (full width) -->
          <div class="gradient-picker__field">
            <label class="inspector-label">CSS override</label>
            <input
              type="text"
              :value="gradient.css_override ?? ''"
              placeholder="linear-gradient(...)"
              class="inspector-control inspector-control--sm inspector-control--mono"
              @input="updateLayer(index, { css_override: ($event.target as HTMLInputElement).value })"
            >
          </div>
        </section>

        <div class="gradient-picker__actions">
          <button
            v-if="gradients.length === 1"
            type="button"
            class="gradient-picker__add"
            @click="addSecondGradient"
          >+ Add second gradient</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.gradient-picker {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.gradient-picker__trigger {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.375rem 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  background: #fff;
  cursor: pointer;
}

.gradient-picker__trigger:hover {
  border-color: #9ca3af;
}

.gradient-picker__trigger--open {
  border-color: var(--c-primary-400, #818cf8);
  box-shadow: 0 0 0 1px var(--c-primary-400, #818cf8);
}

.gradient-picker__trigger-swatch {
  flex: 1;
  height: 1.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.gradient-picker__trigger-swatch--empty {
  background: repeating-linear-gradient(
    45deg,
    #f3f4f6,
    #f3f4f6 4px,
    #e5e7eb 4px,
    #e5e7eb 8px
  );
}

.gradient-picker__trigger-empty {
  font-size: 0.625rem;
  font-weight: 600;
  text-transform: uppercase;
  color: #6b7280;
  letter-spacing: 0.05em;
}

.gradient-picker__trigger-caret {
  flex-shrink: 0;
  color: #9ca3af;
  font-size: 0.75rem;
}

.gradient-picker__panel {
  margin-top: 0.5rem;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  background: #fff;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.gradient-picker__body {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.gradient-picker__section {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.gradient-picker__section--editor {
  padding: 0.5rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.25rem;
  background: #f9fafb;
}

.gradient-picker__section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.gradient-picker__remove {
  width: 1.25rem;
  height: 1.25rem;
  border: none;
  border-radius: 50%;
  background: #e5e7eb;
  color: #6b7280;
  font-size: 0.75rem;
  line-height: 1.25rem;
  text-align: center;
  cursor: pointer;
  padding: 0;
}

.gradient-picker__remove:hover {
  background: #ef4444;
  color: #fff;
}

.gradient-picker__presets {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.375rem;
}

.gradient-picker__preset {
  height: 1.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  cursor: pointer;
  padding: 0;
}

.gradient-picker__preset:hover {
  border-color: var(--c-primary-400, #818cf8);
  box-shadow: 0 0 0 1px var(--c-primary-400, #818cf8);
}

.gradient-picker__preset--none {
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.gradient-picker__preset--none svg {
  width: 100%;
  height: 100%;
  display: block;
}

.gradient-picker__preset--none:hover {
  border-color: #9ca3af;
}

/* Row layout for paired controls */
.gradient-picker__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
  align-items: end;
}

.gradient-picker__row-color {
  min-width: 0;
}

.gradient-picker__row-color :deep(.color-picker__popover) {
  right: auto;
  width: calc(200% + 0.5rem);
}

.gradient-picker__row-opacity {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.gradient-picker__row-half {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.gradient-picker__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.gradient-picker__actions {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.gradient-picker__add {
  padding: 0.375rem 0.5rem;
  border: 1px dashed #d1d5db;
  border-radius: 0.25rem;
  background: #fff;
  font-size: 0.75rem;
  color: #4b5563;
  cursor: pointer;
}

.gradient-picker__add:hover {
  border-color: var(--c-primary-400, #818cf8);
  color: var(--c-primary-600, #4f46e5);
}
</style>
