<script setup lang="ts">
import { computed } from 'vue'

export interface SpacingValue {
  top: number | string | null
  right: number | string | null
  bottom: number | string | null
  left: number | string | null
}

const props = defineProps<{
  modelValue: Partial<SpacingValue> | null | undefined
  label?: string
  unit?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: SpacingValue]
}>()

const sides = ['top', 'right', 'bottom', 'left'] as const
type Side = typeof sides[number]

const current = computed<SpacingValue>(() => ({
  top:    props.modelValue?.top    ?? null,
  right:  props.modelValue?.right  ?? null,
  bottom: props.modelValue?.bottom ?? null,
  left:   props.modelValue?.left   ?? null,
}))

const allValue = computed(() => {
  const { top, right, bottom, left } = current.value
  const normalised = [top, right, bottom, left].map(v => v === null ? '' : String(v))
  const [t, r, b, l] = normalised
  return (t === r && r === b && b === l && t !== '') ? t : ''
})

const allPlaceholder = computed(() => {
  const { top, right, bottom, left } = current.value
  const normalised = [top, right, bottom, left].map(v => v === null ? '' : String(v))
  const [t, r, b, l] = normalised
  return (t === r && r === b && b === l) ? '' : 'mixed'
})

function setSide(side: Side, raw: string) {
  const value = raw === '' ? null : raw
  emit('update:modelValue', { ...current.value, [side]: value })
}

function setAll(raw: string) {
  const value = raw === '' ? null : raw
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
}

.spacing-input__label {
  margin-bottom: 0.5rem;
}

.spacing-input__grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.5rem;
}

.spacing-input__field-label {
  text-align: center;
  color: #9ca3af;
}

.spacing-input__input {
  padding: 0.25rem 0.375rem;
  text-align: center;
}
</style>
