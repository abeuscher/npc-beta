<script setup lang="ts">
import { computed } from 'vue'

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
  familyInheritsFrom?: string | null
  familyInheritLabel?: string | null
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

function useParentFamily() {
  if (!props.familyInheritsFrom) return
  patch({ family: props.familyInheritsFrom })
}

const matchesParent = computed(() =>
  !!props.familyInheritsFrom && props.modelValue.family === props.familyInheritsFrom,
)
</script>

<template>
  <div class="font-input">
    <div class="font-input__row">
      <label class="inspector-label font-input__label">Family</label>
      <div class="font-input__family-wrap">
        <select
          class="inspector-control font-input__control"
          :value="modelValue.family"
          @change="patch({ family: ($event.target as HTMLSelectElement).value })"
        >
          <option v-for="f in families" :key="f.value" :value="f.value">{{ f.label }}</option>
        </select>
        <button
          v-if="familyInheritsFrom"
          type="button"
          class="font-input__use-parent"
          :disabled="matchesParent"
          :title="matchesParent
            ? `Already using ${familyInheritLabel ?? 'parent'} family`
            : `Use ${familyInheritLabel ?? 'parent'} family`"
          @click="useParentFamily"
        >Use {{ familyInheritLabel ?? 'parent' }}</button>
      </div>
    </div>

    <div class="font-input__row font-input__row--split">
      <div class="font-input__field">
        <label class="inspector-label font-input__label">Weight</label>
        <select
          class="inspector-control font-input__control"
          :value="modelValue.weight"
          @change="patch({ weight: ($event.target as HTMLSelectElement).value })"
        >
          <option v-for="w in weights" :key="w.value" :value="w.value">{{ w.label }}</option>
        </select>
      </div>
      <div class="font-input__field">
        <label class="inspector-label font-input__label">Case</label>
        <select
          class="inspector-control font-input__control"
          :value="modelValue.case"
          @change="patch({ case: ($event.target as HTMLSelectElement).value })"
        >
          <option v-for="c in caseOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
        </select>
      </div>
    </div>

    <div class="font-input__row font-input__row--split">
      <div class="font-input__field">
        <label class="inspector-label font-input__label">Size</label>
        <div class="font-input__pair">
          <input
            type="number"
            step="0.01"
            :value="modelValue.size.value"
            class="inspector-control font-input__control font-input__control--narrow"
            @input="patchSize({ value: parseNumeric(($event.target as HTMLInputElement).value, modelValue.size.value) })"
          >
          <select
            class="inspector-control font-input__unit"
            :value="modelValue.size.unit"
            @change="patchSize({ unit: ($event.target as HTMLSelectElement).value })"
          >
            <option v-for="u in sizeUnits" :key="u" :value="u">{{ u }}</option>
          </select>
        </div>
      </div>
      <div class="font-input__field">
        <label class="inspector-label font-input__label">Line height</label>
        <input
          type="number"
          step="0.01"
          :value="modelValue.line_height"
          class="inspector-control font-input__control"
          @input="patch({ line_height: parseNumeric(($event.target as HTMLInputElement).value, modelValue.line_height) })"
        >
      </div>
    </div>

    <div class="font-input__row">
      <label class="inspector-label font-input__label">Letter spacing</label>
      <div class="font-input__pair">
        <input
          type="number"
          step="0.01"
          :value="modelValue.letter_spacing.value"
          class="inspector-control font-input__control font-input__control--narrow"
          @input="patchLetterSpacing({ value: parseNumeric(($event.target as HTMLInputElement).value, modelValue.letter_spacing.value) })"
        >
        <select
          class="inspector-control font-input__unit"
          :value="modelValue.letter_spacing.unit"
          @change="patchLetterSpacing({ unit: ($event.target as HTMLSelectElement).value })"
        >
          <option v-for="u in letterSpacingUnits" :key="u" :value="u">{{ u }}</option>
        </select>
      </div>
    </div>
  </div>
</template>

<style scoped>
.font-input {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.font-input__row {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.font-input__row--split {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}

.font-input__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.font-input__pair {
  display: flex;
  gap: 0.25rem;
}

.font-input__control {
  width: 100%;
}

.font-input__control--narrow {
  flex: 1 1 auto;
  min-width: 0;
}

.font-input__unit {
  flex: 0 0 auto;
  width: 4rem;
}

.font-input__family-wrap {
  display: flex;
  gap: 0.25rem;
  align-items: center;
}

.font-input__use-parent {
  flex: 0 0 auto;
  font-size: 0.75rem;
  color: #374151;
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.25rem 0.5rem;
  cursor: pointer;
  white-space: nowrap;
}

.font-input__use-parent:hover:not(:disabled) {
  background: #e5e7eb;
}

.font-input__use-parent:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
