<script setup lang="ts">
export interface FontValue {
  family: string
  weight: string
  size: { value: number, unit: string }
  line_height: number
  letter_spacing: { value: number, unit: string }
  case: string
}

export interface FontFamilyOption {
  value: string
  label: string
}

const props = defineProps<{
  modelValue: FontValue
  families: FontFamilyOption[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: FontValue]
}>()

const weights = [
  { value: '100', label: '100 Thin' },
  { value: '200', label: '200 Extra Light' },
  { value: '300', label: '300 Light' },
  { value: '400', label: '400 Regular' },
  { value: '500', label: '500 Medium' },
  { value: '600', label: '600 Semi-Bold' },
  { value: '700', label: '700 Bold' },
  { value: '800', label: '800 Extra Bold' },
  { value: '900', label: '900 Black' },
]

const caseOptions = [
  { value: 'none',       label: 'None' },
  { value: 'uppercase',  label: 'UPPERCASE' },
  { value: 'lowercase',  label: 'lowercase' },
  { value: 'capitalize', label: 'Capitalize' },
  { value: 'small-caps', label: 'Small Caps' },
]

const sizeUnits = ['px', 'rem', 'em']
const letterSpacingUnits = ['em', 'px']

function patch(partial: Partial<FontValue>) {
  emit('update:modelValue', { ...props.modelValue, ...partial })
}

function patchSize(partial: Partial<FontValue['size']>) {
  emit('update:modelValue', { ...props.modelValue, size: { ...props.modelValue.size, ...partial } })
}

function patchLetterSpacing(partial: Partial<FontValue['letter_spacing']>) {
  emit('update:modelValue', { ...props.modelValue, letter_spacing: { ...props.modelValue.letter_spacing, ...partial } })
}

function parseNumeric(raw: string, fallback: number): number {
  if (raw === '') return fallback
  const n = Number(raw)
  return Number.isFinite(n) ? n : fallback
}
</script>

<template>
  <div class="font-input">
    <div class="font-input__field" style="grid-column: span 3">
      <label class="inspector-label font-input__label">Family</label>
      <select
        class="inspector-control font-input__control"
        :value="modelValue.family"
        @change="patch({ family: ($event.target as HTMLSelectElement).value })"
      >
        <option v-for="f in families" :key="f.value" :value="f.value">{{ f.label }}</option>
      </select>
    </div>
    <div class="font-input__field" style="grid-column: span 2">
      <label class="inspector-label font-input__label">Weight</label>
      <select
        class="inspector-control font-input__control"
        :value="modelValue.weight"
        @change="patch({ weight: ($event.target as HTMLSelectElement).value })"
      >
        <option v-for="w in weights" :key="w.value" :value="w.value">{{ w.label }}</option>
      </select>
    </div>
    <div class="font-input__field" style="grid-column: span 2">
      <label class="inspector-label font-input__label">Case</label>
      <select
        class="inspector-control font-input__control"
        :value="modelValue.case"
        @change="patch({ case: ($event.target as HTMLSelectElement).value })"
      >
        <option v-for="c in caseOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
      </select>
    </div>
    <div class="font-input__field">
      <label class="inspector-label font-input__label">Size</label>
      <input
        type="number"
        step="0.01"
        :value="modelValue.size.value"
        class="inspector-control font-input__control"
        @input="patchSize({ value: parseNumeric(($event.target as HTMLInputElement).value, modelValue.size.value) })"
      >
    </div>
    <div class="font-input__field">
      <label class="inspector-label font-input__label">Unit</label>
      <select
        class="inspector-control font-input__control"
        :value="modelValue.size.unit"
        @change="patchSize({ unit: ($event.target as HTMLSelectElement).value })"
      >
        <option v-for="u in sizeUnits" :key="u" :value="u">{{ u }}</option>
      </select>
    </div>
    <div class="font-input__field">
      <label class="inspector-label font-input__label">Line ht.</label>
      <input
        type="number"
        step="0.01"
        :value="modelValue.line_height"
        class="inspector-control font-input__control"
        @input="patch({ line_height: parseNumeric(($event.target as HTMLInputElement).value, modelValue.line_height) })"
      >
    </div>
    <div class="font-input__field">
      <label class="inspector-label font-input__label">Letter sp.</label>
      <input
        type="number"
        step="0.01"
        :value="modelValue.letter_spacing.value"
        class="inspector-control font-input__control"
        @input="patchLetterSpacing({ value: parseNumeric(($event.target as HTMLInputElement).value, modelValue.letter_spacing.value) })"
      >
    </div>
    <div class="font-input__field">
      <label class="inspector-label font-input__label">Unit</label>
      <select
        class="inspector-control font-input__control"
        :value="modelValue.letter_spacing.unit"
        @change="patchLetterSpacing({ unit: ($event.target as HTMLSelectElement).value })"
      >
        <option v-for="u in letterSpacingUnits" :key="u" :value="u">{{ u }}</option>
      </select>
    </div>
  </div>
</template>

<style scoped>
.font-input {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 0.5rem;
}

.font-input__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.font-input__control {
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}
</style>
