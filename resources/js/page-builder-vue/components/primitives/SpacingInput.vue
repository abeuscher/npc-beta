<script setup lang="ts">
import { computed } from 'vue'

export interface SpacingValue {
  top: number
  right: number
  bottom: number
  left: number
}

const props = defineProps<{
  modelValue: Partial<Record<keyof SpacingValue, number | string | null>> | null | undefined
  label?: string
  unit?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: SpacingValue]
}>()

const sides = ['top', 'right', 'bottom', 'left'] as const
type Side = typeof sides[number]

function coerce(v: number | string | null | undefined): number {
  if (v === null || v === undefined || v === '') return 0
  const n = typeof v === 'number' ? v : parseInt(v, 10)
  return Number.isFinite(n) ? n : 0
}

const current = computed<SpacingValue>(() => ({
  top:    coerce(props.modelValue?.top),
  right:  coerce(props.modelValue?.right),
  bottom: coerce(props.modelValue?.bottom),
  left:   coerce(props.modelValue?.left),
}))

const allValue = computed(() => {
  const { top, right, bottom, left } = current.value
  return (top === right && right === bottom && bottom === left) ? String(top) : ''
})

const allPlaceholder = computed(() => {
  const { top, right, bottom, left } = current.value
  return (top === right && right === bottom && bottom === left) ? '' : 'mixed'
})

function setSide(side: Side, raw: string) {
  emit('update:modelValue', { ...current.value, [side]: coerce(raw) })
}

function setAll(raw: string) {
  const value = coerce(raw)
  emit('update:modelValue', { top: value, right: value, bottom: value, left: value })
}

const sideOrder: { key: Side, label: string }[] = [
  { key: 'left',   label: 'Left' },
  { key: 'top',    label: 'Top' },
  { key: 'right',  label: 'Right' },
  { key: 'bottom', label: 'Bottom' },
]
</script>

<template>
  <div class="spacing-input">
    <p v-if="label" class="inspector-section-title spacing-input__label">{{ label }}<span v-if="unit"> ({{ unit }})</span></p>
    <div class="spacing-input__grid">
      <div class="spacing-input__field">
        <label class="inspector-label spacing-input__field-label">All</label>
        <input
          type="number"
          min="0"
          :value="allValue"
          :placeholder="allPlaceholder"
          class="inspector-control spacing-input__input"
          @input="setAll(($event.target as HTMLInputElement).value)"
        >
      </div>
      <div v-for="item in sideOrder" :key="item.key" class="spacing-input__field">
        <label class="inspector-label spacing-input__field-label">{{ item.label }}</label>
        <input
          type="number"
          min="0"
          :value="current[item.key] ?? ''"
          class="inspector-control spacing-input__input"
          @input="setSide(item.key, ($event.target as HTMLInputElement).value)"
        >
      </div>
    </div>
  </div>
</template>

<style scoped>
.spacing-input {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.spacing-input__label {
  margin-bottom: 0.5rem;
}

.spacing-input__grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.25rem;
}

.spacing-input__field {
  min-width: 0;
}

.spacing-input__field-label {
  text-align: center;
  color: #9ca3af;
}

.spacing-input__input {
  width: 100%;
  min-width: 0;
  padding: 0.25rem 0.25rem;
  text-align: center;
  box-sizing: border-box;
  -moz-appearance: textfield;
  appearance: textfield;
}

.spacing-input__input::-webkit-outer-spin-button,
.spacing-input__input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
</style>
