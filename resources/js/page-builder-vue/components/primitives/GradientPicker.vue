<script setup lang="ts">
import { computed, ref } from 'vue'
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
  }>(),
  {
    modelValue: null,
    label: '',
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
  { label: 'Lavender Sky',  layer: { type: 'linear', from: '#e0c3fc', to: '#8ec5fc', angle: 180 } },
  { label: 'Sunrise',       layer: { type: 'linear', from: '#ff9a9e', to: '#fad0c4', angle: 180 } },
  { label: 'Calm Sky',      layer: { type: 'linear', from: '#a1c4fd', to: '#c2e9fb', angle: 180 } },
  { label: 'Mint Meadow',   layer: { type: 'linear', from: '#d4fc79', to: '#96e6a1', angle: 180 } },
  { label: 'Dusk',          layer: { type: 'linear', from: '#2c3e50', to: '#4ca1af', angle: 180 } },
  { label: 'Sunset',        layer: { type: 'linear', from: '#ff7e5f', to: '#feb47b', angle: 180 } },
  { label: 'Slate',         layer: { type: 'linear', from: '#232526', to: '#414345', angle: 180 } },
  { label: 'Aurora',        layer: { type: 'linear', from: '#00c6ff', to: '#0072ff', angle: 180 } },
]

const DEFAULT_LAYER: GradientLayer = {
  type: 'linear',
  from: '#ffffff',
  to: '#000000',
  angle: 180,
}

const isOpen = ref(false)

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

function createInitialGradient(): void {
  emitGradients([{ ...DEFAULT_LAYER }])
}

function applyPreset(preset: GradientPreset): void {
  // Replaces the first gradient with the preset; preserves a Gradient 2 if present.
  const next = [...gradients.value]
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
  <div class="gradient-picker">
    <label v-if="label" class="gradient-picker__label">{{ label }}</label>

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

    <div v-if="isOpen" class="gradient-picker__panel">
      <div v-if="!hasGradient" class="gradient-picker__empty">
        <p>No gradient set.</p>
        <button type="button" class="gradient-picker__create" @click="createInitialGradient">
          Create gradient
        </button>
      </div>

      <div v-else class="gradient-picker__body">
        <div class="gradient-picker__controls">
          <section class="gradient-picker__section">
            <p class="gradient-picker__section-title">Presets</p>
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
            </div>
          </section>

          <section
            v-for="(gradient, index) in gradients"
            :key="index"
            class="gradient-picker__section gradient-picker__section--editor"
          >
            <div class="gradient-picker__section-head">
              <p class="gradient-picker__section-title">
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

            <div class="gradient-picker__field">
              <ColorPicker
                :model-value="gradient.from"
                label="From"
                @update:model-value="updateLayer(index, { from: $event })"
              />
            </div>
            <div class="gradient-picker__field">
              <ColorPicker
                :model-value="gradient.to"
                label="To"
                @update:model-value="updateLayer(index, { to: $event })"
              />
            </div>

            <div class="gradient-picker__field">
              <label class="gradient-picker__inline-label">Type</label>
              <select
                :value="gradient.type"
                class="gradient-picker__select"
                @change="updateLayer(index, { type: ($event.target as HTMLSelectElement).value as GradientLayer['type'] })"
              >
                <option value="linear">Linear</option>
                <option value="radial">Radial</option>
              </select>
            </div>

            <div v-if="gradient.type === 'linear'" class="gradient-picker__field">
              <label class="gradient-picker__inline-label">Angle (deg)</label>
              <input
                type="number"
                min="0"
                max="360"
                :value="gradient.angle ?? 180"
                class="gradient-picker__number"
                @input="updateLayer(index, { angle: parseInt(($event.target as HTMLInputElement).value, 10) || 0 })"
              >
            </div>

            <div class="gradient-picker__field">
              <label class="gradient-picker__inline-label">CSS override</label>
              <input
                type="text"
                :value="gradient.css_override ?? ''"
                placeholder="linear-gradient(...)"
                class="gradient-picker__text"
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
            <button
              type="button"
              class="gradient-picker__clear"
              @click="clearAll"
            >Clear gradient</button>
          </div>
        </div>

        <div class="gradient-picker__preview">
          <p class="gradient-picker__section-title">Preview</p>
          <div
            class="gradient-picker__preview-swatch"
            :style="previewCss ? { backgroundImage: previewCss } : undefined"
          />
          <code class="gradient-picker__preview-css">{{ previewCss || '(empty)' }}</code>
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

.gradient-picker__label {
  font-size: 0.75rem;
  font-weight: 500;
  color: #4b5563;
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

.gradient-picker__empty {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.5rem;
  font-size: 0.75rem;
  color: #6b7280;
}

.gradient-picker__empty p {
  margin: 0;
}

.gradient-picker__create {
  padding: 0.375rem 0.75rem;
  border: 1px solid var(--c-primary-400, #818cf8);
  border-radius: 0.25rem;
  background: #fff;
  font-size: 0.75rem;
  color: var(--c-primary-600, #4f46e5);
  cursor: pointer;
}

.gradient-picker__body {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 7rem;
  gap: 0.75rem;
}

.gradient-picker__controls {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-width: 0;
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

.gradient-picker__section-title {
  margin: 0;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
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

.gradient-picker__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.gradient-picker__inline-label {
  font-size: 0.6875rem;
  font-weight: 500;
  color: #4b5563;
}

.gradient-picker__select,
.gradient-picker__number,
.gradient-picker__text {
  width: 100%;
  padding: 0.25rem 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  background: #fff;
  font-size: 0.75rem;
  color: #1f2937;
}

.gradient-picker__text {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.gradient-picker__select:focus,
.gradient-picker__number:focus,
.gradient-picker__text:focus {
  outline: none;
  border-color: var(--c-primary-400, #818cf8);
  box-shadow: 0 0 0 1px var(--c-primary-400, #818cf8);
}

.gradient-picker__actions {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.gradient-picker__add,
.gradient-picker__clear {
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

.gradient-picker__clear:hover {
  border-color: #ef4444;
  color: #ef4444;
}

.gradient-picker__preview {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.gradient-picker__preview-swatch {
  width: 100%;
  height: 6rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  background: repeating-linear-gradient(
    45deg,
    #f3f4f6,
    #f3f4f6 4px,
    #e5e7eb 4px,
    #e5e7eb 8px
  );
}

.gradient-picker__preview-css {
  font-size: 0.625rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  color: #6b7280;
  word-break: break-all;
  line-height: 1.3;
}
</style>
