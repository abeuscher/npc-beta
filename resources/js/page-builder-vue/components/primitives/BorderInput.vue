<script setup lang="ts">
import { computed } from 'vue'
import ColorPicker from './ColorPicker.vue'

export interface BorderValue {
  top: boolean
  right: boolean
  bottom: boolean
  left: boolean
  width: number
  color: string
  radius: number
}

const props = defineProps<{
  modelValue: Partial<BorderValue> | null | undefined
  label?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: BorderValue]
}>()

const sides = ['top', 'right', 'bottom', 'left'] as const
type Side = typeof sides[number]

function coerceInt(v: number | string | null | undefined): number {
  if (v === null || v === undefined || v === '') return 0
  const n = typeof v === 'number' ? v : parseInt(v, 10)
  return Number.isFinite(n) && n > 0 ? n : 0
}

const current = computed<BorderValue>(() => ({
  top:    !!props.modelValue?.top,
  right:  !!props.modelValue?.right,
  bottom: !!props.modelValue?.bottom,
  left:   !!props.modelValue?.left,
  width:  coerceInt(props.modelValue?.width),
  color:  props.modelValue?.color ?? '#000000',
  radius: coerceInt(props.modelValue?.radius),
}))

function toggleSide(side: Side): void {
  emit('update:modelValue', { ...current.value, [side]: !current.value[side] })
}

function setWidth(raw: string): void {
  emit('update:modelValue', { ...current.value, width: coerceInt(raw) })
}

function setRadius(raw: string): void {
  emit('update:modelValue', { ...current.value, radius: coerceInt(raw) })
}

function setColor(hex: string): void {
  emit('update:modelValue', { ...current.value, color: hex })
}

// Edge geometry inside the 48-unit viewBox — matches NinePointAlignment's frame.
const edges: { key: Side; x1: number; y1: number; x2: number; y2: number }[] = [
  { key: 'top',    x1: 8,  y1: 8,  x2: 40, y2: 8 },
  { key: 'right',  x1: 40, y1: 8,  x2: 40, y2: 40 },
  { key: 'bottom', x1: 8,  y1: 40, x2: 40, y2: 40 },
  { key: 'left',   x1: 8,  y1: 8,  x2: 8,  y2: 40 },
]
</script>

<template>
  <div class="border-input">
    <p v-if="label" class="inspector-section-title border-input__label">{{ label }}</p>
    <div class="border-input__row">
      <div
        class="border-input__box"
        role="group"
        aria-label="Border edges"
      >
        <svg
          class="border-input__svg"
          viewBox="0 0 48 48"
          xmlns="http://www.w3.org/2000/svg"
          aria-hidden="true"
        >
          <rect x="1" y="1" width="46" height="46" rx="4" ry="4" class="border-input__bg" />
          <g
            v-for="edge in edges"
            :key="edge.key"
            class="border-input__edge"
            :class="{ 'border-input__edge--on': current[edge.key] }"
            @click="toggleSide(edge.key)"
          >
            <line
              :x1="edge.x1" :y1="edge.y1" :x2="edge.x2" :y2="edge.y2"
              class="border-input__hit"
            />
            <line
              :x1="edge.x1" :y1="edge.y1" :x2="edge.x2" :y2="edge.y2"
              class="border-input__line"
            />
            <title>{{ edge.key }}</title>
          </g>
        </svg>
      </div>

      <div class="border-input__color">
        <label class="inspector-label border-input__field-label">Color</label>
        <ColorPicker
          :model-value="current.color"
          compact
          @update:model-value="setColor"
        />
      </div>

      <div class="border-input__field">
        <label class="inspector-label border-input__field-label">Width</label>
        <input
          type="number"
          min="0"
          :value="current.width"
          class="inspector-control border-input__input"
          @input="setWidth(($event.target as HTMLInputElement).value)"
        >
      </div>

      <div class="border-input__field">
        <label class="inspector-label border-input__field-label">Radius</label>
        <input
          type="number"
          min="0"
          :value="current.radius"
          class="inspector-control border-input__input"
          @input="setRadius(($event.target as HTMLInputElement).value)"
        >
      </div>
    </div>
  </div>
</template>

<style scoped>
.border-input {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.border-input__label {
  margin-bottom: 0.5rem;
}

.border-input__row {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
}

.border-input__box {
  width: 2.2rem;
  height: 2.2rem;
  flex-shrink: 0;
}

.border-input__svg {
  width: 100%;
  height: 100%;
  display: block;
}

.border-input__bg {
  fill: #f9fafb;
  stroke: #d1d5db;
  stroke-width: 1;
}

.border-input__edge {
  cursor: pointer;
}

.border-input__hit {
  stroke: transparent;
  stroke-width: 8;
  stroke-linecap: round;
}

.border-input__line {
  stroke: #9ca3af;
  stroke-width: 2.5;
  stroke-linecap: round;
  transition: stroke 0.1s ease;
}

.border-input__edge:hover .border-input__line {
  stroke: #6b7280;
}

.border-input__edge--on .border-input__line {
  stroke: var(--c-primary-600, #4f46e5);
}

.border-input__color {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.border-input__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
  flex: 1;
}

.border-input__field-label {
  text-align: center;
  color: #9ca3af;
}

.border-input__input {
  width: 100%;
  min-width: 0;
  padding: 0.25rem;
  text-align: center;
  box-sizing: border-box;
  -moz-appearance: textfield;
  appearance: textfield;
}

.border-input__input::-webkit-outer-spin-button,
.border-input__input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

html.dark .border-input__bg                        { fill: rgb(31 41 55); stroke: rgb(75 85 99); }
html.dark .border-input__line                      { stroke: rgb(107 114 128); }
html.dark .border-input__edge:hover .border-input__line { stroke: rgb(156 163 175); }
html.dark .border-input__edge--on .border-input__line   { stroke: var(--c-primary-400, #818cf8); }
</style>
