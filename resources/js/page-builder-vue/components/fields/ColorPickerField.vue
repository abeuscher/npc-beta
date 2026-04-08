<script setup lang="ts">
import { computed } from 'vue'
import type { FieldDef } from '../../types'
import { useEditorStore } from '../../stores/editor'

const props = defineProps<{
  field: FieldDef
  modelValue: any
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const store = useEditorStore()

const hasValue = computed(() => !!props.modelValue)

const primaryColor = computed(() => {
  const style = getComputedStyle(document.documentElement)
  return style.getPropertyValue('--color-primary').trim() || '#4f46e5'
})

function addSwatch(color: string) {
  if (!color || store.colorSwatches.includes(color)) return
  store.saveColorSwatches([...store.colorSwatches, color])
}

function removeSwatch(index: number) {
  const updated = store.colorSwatches.filter((_, i) => i !== index)
  store.saveColorSwatches(updated)
}

function clearColor() {
  emit('update:modelValue', '')
}
</script>

<template>
  <div class="color-picker">
    <div class="color-picker__inputs">
      <div class="color-picker__wheel-wrap">
        <input
          type="color"
          :value="modelValue || '#888888'"
          class="color-picker__wheel"
          :class="{ 'color-picker__wheel--empty': !hasValue }"
          @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        >
        <span v-if="!hasValue" class="color-picker__empty-indicator">?</span>
      </div>
      <input
        type="text"
        :value="modelValue ?? ''"
        :placeholder="field.helper ?? 'No colour set'"
        class="color-picker__hex"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      >
      <button
        v-if="hasValue"
        type="button"
        class="color-picker__clear"
        title="Clear colour"
        @click="clearColor"
      >&times;</button>
    </div>

    <div class="color-picker__swatches">
      <button
        type="button"
        class="color-picker__swatch color-picker__swatch--preset"
        :style="{ backgroundColor: primaryColor }"
        :title="'Theme primary: ' + primaryColor"
        @click="emit('update:modelValue', primaryColor)"
      />

      <button
        v-for="(swatch, index) in store.colorSwatches"
        :key="index"
        type="button"
        class="color-picker__swatch"
        :style="{ backgroundColor: swatch }"
        :title="swatch"
        @click="emit('update:modelValue', swatch)"
      >
        <span
          class="color-picker__swatch-remove"
          title="Remove swatch"
          @click.stop="removeSwatch(index)"
        >&times;</span>
      </button>

      <button
        type="button"
        class="color-picker__swatch color-picker__swatch--add"
        title="Save current colour as swatch"
        @click="addSwatch(modelValue)"
      >+</button>
    </div>
  </div>
</template>

<style scoped>
.color-picker__inputs {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.color-picker__wheel-wrap {
  position: relative;
  flex-shrink: 0;
}

.color-picker__wheel {
  width: 2rem;
  height: 2rem;
  padding: 0;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  cursor: pointer;
  background: none;
}

.color-picker__wheel--empty {
  opacity: 0.3;
}

.color-picker__empty-indicator {
  position: absolute;
  top: 0;
  left: 0;
  width: 2rem;
  height: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  color: #6b7280;
  pointer-events: none;
}

.color-picker__clear {
  flex-shrink: 0;
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

.color-picker__clear:hover {
  background: #ef4444;
  color: #fff;
}

.color-picker__hex {
  flex: 1;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.375rem 0.5rem;
  font-size: 0.875rem;
  color: #1f2937;
  background: #fff;
}

.color-picker__hex:focus {
  outline: none;
  border-color: var(--c-primary-400, #818cf8);
  box-shadow: 0 0 0 1px var(--c-primary-400, #818cf8);
}

.color-picker__swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
  margin-top: 0.5rem;
}

.color-picker__swatch {
  position: relative;
  width: 1.5rem;
  height: 1.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  cursor: pointer;
  padding: 0;
}

.color-picker__swatch--preset {
  border-width: 2px;
  border-color: #9ca3af;
}

.color-picker__swatch--add {
  display: flex;
  align-items: center;
  justify-content: center;
  border-style: dashed;
  font-size: 0.875rem;
  color: #9ca3af;
  background: #fff;
}

.color-picker__swatch--add:hover {
  border-color: var(--c-primary-400, #818cf8);
  color: var(--c-primary-600, #4f46e5);
}

.color-picker__swatch-remove {
  display: none;
  position: absolute;
  top: -0.375rem;
  right: -0.375rem;
  width: 0.875rem;
  height: 0.875rem;
  border-radius: 50%;
  background: #ef4444;
  color: #fff;
  font-size: 0.625rem;
  line-height: 0.875rem;
  text-align: center;
  cursor: pointer;
}

.color-picker__swatch:hover .color-picker__swatch-remove {
  display: block;
}
</style>
