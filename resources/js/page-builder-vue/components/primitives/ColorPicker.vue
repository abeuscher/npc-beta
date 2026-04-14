<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useEditorStore } from '../../stores/editor'

const props = withDefaults(
  defineProps<{
    modelValue?: string
    label?: string
    placeholder?: string
    compact?: boolean
  }>(),
  {
    modelValue: '',
    label: '',
    placeholder: 'Transparent',
    compact: false,
  }
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const store = useEditorStore()

const isOpen = ref(false)
const rootEl = ref<HTMLElement | null>(null)

const hasValue = computed(() => !!props.modelValue)

function openPopover(): void {
  isOpen.value = true
}

function closePopover(): void {
  isOpen.value = false
}

function togglePopover(): void {
  if (isOpen.value) {
    closePopover()
  } else {
    openPopover()
  }
}

function selectColor(hex: string): void {
  emit('update:modelValue', hex)
  closePopover()
}

function clearColor(): void {
  emit('update:modelValue', '')
  closePopover()
}

function onWheelInput(e: Event): void {
  emit('update:modelValue', (e.target as HTMLInputElement).value)
}

function onHexInput(e: Event): void {
  emit('update:modelValue', (e.target as HTMLInputElement).value)
}

function addCurrentSwatch(): void {
  const current = props.modelValue
  if (!current) return
  if (store.colorSwatches.includes(current)) return
  store.saveColorSwatches([...store.colorSwatches, current])
}

function removeSwatch(index: number): void {
  const updated = store.colorSwatches.filter((_, i) => i !== index)
  store.saveColorSwatches(updated)
}

function onDocumentClick(e: MouseEvent): void {
  if (!isOpen.value) return
  if (!rootEl.value) return
  if (rootEl.value.contains(e.target as Node)) return
  closePopover()
}

function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape' && isOpen.value) {
    closePopover()
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
</script>

<template>
  <div ref="rootEl" class="color-picker">
    <label v-if="label" class="inspector-label">{{ label }}</label>
    <button
      type="button"
      class="color-picker__trigger"
      :class="{
        'color-picker__trigger--compact': compact,
      }"
      @click="togglePopover"
    >
      <span
        class="color-picker__trigger-swatch"
        :class="{ 'color-picker__trigger-swatch--empty': !hasValue }"
        :style="hasValue ? { backgroundColor: modelValue } : undefined"
      >
        <slot name="icon" />
        <span v-if="!hasValue" class="color-picker__trigger-empty">?</span>
      </span>
      <span v-if="!compact" class="color-picker__trigger-text">
        {{ hasValue ? modelValue : placeholder }}
      </span>
      <span v-if="!compact" class="color-picker__trigger-caret" aria-hidden="true">▾</span>
    </button>

    <div v-if="isOpen" class="color-picker__popover" role="dialog" aria-label="Color picker">
      <div v-if="store.themePalette.length > 0" class="color-picker__group">
        <p class="inspector-section-title">Theme colors</p>
        <div class="color-picker__swatches">
          <button
            v-for="entry in store.themePalette"
            :key="entry.key"
            type="button"
            class="color-picker__swatch color-picker__swatch--theme"
            :class="{ 'color-picker__swatch--unset': !entry.value }"
            :style="entry.value ? { backgroundColor: entry.value } : undefined"
            :title="entry.value ? `${entry.label}: ${entry.value}` : `${entry.label}: (unset)`"
            :disabled="!entry.value"
            @click="entry.value && selectColor(entry.value)"
          >
            <span class="color-picker__swatch-sr">{{ entry.label }}</span>
          </button>
          <button
            type="button"
            class="color-picker__swatch color-picker__swatch--no-color"
            title="No color"
            @click="clearColor"
          >
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <line x1="4" y1="4" x2="20" y2="20" stroke="#dc2626" stroke-width="3" stroke-linecap="round" />
            </svg>
            <span class="color-picker__swatch-sr">No color</span>
          </button>
        </div>
      </div>

      <hr v-if="store.themePalette.length > 0" class="color-picker__divider" />

      <div class="color-picker__group">
        <p class="inspector-section-title">My swatches</p>
        <div class="color-picker__swatches">
          <button
            v-for="(swatch, index) in store.colorSwatches"
            :key="index"
            type="button"
            class="color-picker__swatch"
            :style="{ backgroundColor: swatch }"
            :title="swatch"
            @click="selectColor(swatch)"
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
            title="Save current color as swatch"
            :disabled="!hasValue"
            @click="addCurrentSwatch"
          >+</button>
        </div>
      </div>

      <hr class="color-picker__divider" />

      <div class="color-picker__group">
        <p class="inspector-section-title">Add custom color</p>
        <div class="color-picker__freeform">
          <input
            type="color"
            :value="modelValue || '#888888'"
            class="color-picker__wheel"
            @input="onWheelInput"
          >
          <input
            type="text"
            :value="modelValue ?? ''"
            :placeholder="placeholder"
            class="inspector-control inspector-control--mono color-picker__hex"
            @input="onHexInput"
          >
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.color-picker {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.color-picker__trigger {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.375rem 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  background: #fff;
  font-size: 0.875rem;
  color: #1f2937;
  cursor: pointer;
}

.color-picker__trigger:hover {
  border-color: #9ca3af;
}

.color-picker__trigger--compact {
  width: auto;
  padding: 0;
  border: none;
  background: none;
}

.color-picker__trigger--compact .color-picker__trigger-swatch {
  width: 2rem;
  height: 2rem;
}

.color-picker__trigger-swatch {
  position: relative;
  flex-shrink: 0;
  width: 1.5rem;
  height: 1.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.color-picker__trigger-swatch--empty {
  background: repeating-linear-gradient(
    45deg,
    #f3f4f6,
    #f3f4f6 4px,
    #e5e7eb 4px,
    #e5e7eb 8px
  );
}

.color-picker__trigger-empty {
  font-size: 0.625rem;
  font-weight: 700;
  color: #6b7280;
}

.color-picker__trigger-text {
  flex: 1;
  text-align: left;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.65rem;
}

.color-picker__trigger-caret {
  flex-shrink: 0;
  color: #9ca3af;
  font-size: 0.65rem;
  margin-left: -8px;
}

.color-picker__popover {
  position: absolute;
  top: calc(100% + 0.25rem);
  left: 0;
  right: 0;
  z-index: 50;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  background: #fff;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.color-picker__group + .color-picker__group {
  margin-top: 0.5rem;
}

.color-picker__swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
}

.color-picker__swatch {
  position: relative;
  width: 1.5rem;
  height: 1.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  cursor: pointer;
  padding: 0;
  background: #fff;
}

.color-picker__swatch--theme {
  border-width: 2px;
}

.color-picker__swatch--unset {
  background: repeating-linear-gradient(
    45deg,
    #f9fafb,
    #f9fafb 3px,
    #e5e7eb 3px,
    #e5e7eb 6px
  );
  cursor: not-allowed;
}

.color-picker__swatch-sr {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.color-picker__swatch--add {
  display: flex;
  align-items: center;
  justify-content: center;
  border-style: dashed;
  font-size: 0.875rem;
  color: #9ca3af;
}

.color-picker__swatch--add:hover:not(:disabled) {
  border-color: var(--c-primary-400, #818cf8);
  color: var(--c-primary-600, #4f46e5);
}

.color-picker__swatch--add:disabled {
  opacity: 0.4;
  cursor: not-allowed;
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

.color-picker__divider {
  margin: 0.625rem 0;
  border: none;
  border-top: 1px solid #e5e7eb;
}

.color-picker__swatch--no-color {
  background: #fff;
  border-color: #d1d5db;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.color-picker__swatch--no-color svg {
  width: 100%;
  height: 100%;
  display: block;
}

.color-picker__swatch--no-color:hover {
  border-color: #9ca3af;
}

.color-picker__freeform {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.color-picker__wheel {
  width: 1.5rem;
  height: 1.5rem;
  flex-shrink: 0;
  padding: 0;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  cursor: pointer;
  background: none;
}

.color-picker__wheel::-webkit-color-swatch-wrapper {
  padding: 0;
}

.color-picker__wheel::-webkit-color-swatch {
  border: none;
  border-radius: 0.175rem;
}

.color-picker__wheel::-moz-color-swatch {
  border: none;
  border-radius: 0.175rem;
}

.color-picker__hex {
  flex: 1;
}
</style>
