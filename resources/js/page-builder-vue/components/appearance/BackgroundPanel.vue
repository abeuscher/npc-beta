<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Widget } from '../../types'
import { useEditorStore } from '../../stores/editor'
import ColorPicker from '../primitives/ColorPicker.vue'
import GradientPicker from '../primitives/GradientPicker.vue'
import NinePointAlignment from '../primitives/NinePointAlignment.vue'
import { composeGradientCss } from '../../helpers/gradient'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()
const uploading = ref(false)
const gradientPickerRef = ref<InstanceType<typeof GradientPicker> | null>(null)

const backgroundColor = computed(() => props.widget.appearance_config?.background?.color ?? '#ffffff')
const gradient = computed(() => props.widget.appearance_config?.background?.gradient ?? null)
const alignment = computed(() => props.widget.appearance_config?.background?.alignment ?? 'center')
const fit = computed(() => props.widget.appearance_config?.background?.fit ?? 'cover')
const imageUrl = computed(() => props.widget.appearance_image_url ?? null)
const useCurrentPageHeader = computed(() => props.widget.appearance_config?.background?.use_current_page_header ?? false)
const hasImage = computed(() => imageUrl.value !== null)
const imageControlsDisabled = computed(() => useCurrentPageHeader.value || !hasImage.value)

const hasGradient = computed(() => Array.isArray(gradient.value?.gradients) && gradient.value!.gradients.length > 0)
const gradientPreviewCss = computed(() => composeGradientCss(gradient.value ?? null))
const gradientSwatchStyle = computed(() => {
  if (!hasGradient.value || gradientPreviewCss.value === '') return undefined
  return { backgroundImage: gradientPreviewCss.value }
})

function updateAppearance(path: string, value: any) {
  store.updateLocalAppearanceConfig(props.widget.id, path, value)
}

function toggleGradientPanel() {
  if (gradientPickerRef.value) {
    gradientPickerRef.value.isOpen = !gradientPickerRef.value.isOpen
  }
}

async function handleImageUpload(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  uploading.value = true
  try {
    await store.uploadAppearanceImage(props.widget.id, file)
  } finally {
    uploading.value = false
    input.value = ''
  }
}

async function handleImageRemove() {
  await store.removeAppearanceImage(props.widget.id)
}

function triggerFileInput() {
  const input = document.getElementById(`bg-upload-${props.widget.id}`) as HTMLInputElement | null
  input?.click()
}
</script>

<template>
  <div class="bg-panel">
    <p class="bg-panel__heading">Background</p>

    <!-- Top row: Color, Gradient, Image -->
    <div class="bg-panel__row">
      <div class="bg-panel__row-cell">
        <label class="inspector-label">Color</label>
        <ColorPicker
          :model-value="backgroundColor"
          compact
          @update:model-value="updateAppearance('background.color', $event)"
        />
      </div>

      <div class="bg-panel__row-cell">
        <label class="inspector-label">Gradient</label>
        <button
          type="button"
          class="bg-panel__gradient-swatch"
          :class="{ 'bg-panel__gradient-swatch--empty': !hasGradient }"
          :style="gradientSwatchStyle"
          @click.stop="toggleGradientPanel"
        />
      </div>

      <div class="bg-panel__row-cell">
        <label class="inspector-label">Image</label>
        <input
          :id="`bg-upload-${widget.id}`"
          type="file"
          accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
          class="bg-panel__file-input"
          @change="handleImageUpload"
        >
        <div v-if="hasImage" class="bg-panel__image-thumb" @click="triggerFileInput">
          <img :src="imageUrl!" alt="Background image" />
          <button
            type="button"
            class="bg-panel__image-remove"
            title="Remove image"
            @click.stop="handleImageRemove"
          >&times;</button>
        </div>
        <button
          v-else
          type="button"
          class="bg-panel__upload-tile"
          :disabled="uploading"
          @click="triggerFileInput"
        >
          <span v-if="uploading">…</span>
          <span v-else class="bg-panel__upload-icon">+</span>
        </button>
      </div>

      <div class="bg-panel__row-cell">
        <label class="inspector-label">&nbsp;</label>
        <NinePointAlignment
          :model-value="alignment"
          :disabled="imageControlsDisabled"
          @update:model-value="updateAppearance('background.alignment', $event)"
        />
      </div>

      <div class="bg-panel__row-cell">
        <label class="inspector-label">Fit</label>
        <select
          :value="fit"
          class="inspector-control inspector-control--sm"
          :disabled="imageControlsDisabled"
          @change="updateAppearance('background.fit', ($event.target as HTMLSelectElement).value)"
        >
          <option value="cover">Cover</option>
          <option value="contain">Contain</option>
        </select>
      </div>
    </div>

    <!-- Override: use current page's header image instead of the uploaded image -->
    <label class="bg-panel__override">
      <input
        type="checkbox"
        :checked="useCurrentPageHeader"
        @change="updateAppearance('background.use_current_page_header', ($event.target as HTMLInputElement).checked)"
      >
      <span>Use current page's header image</span>
    </label>

    <!-- Gradient panel (full width, normal flow below the row) -->
    <GradientPicker
      ref="gradientPickerRef"
      :model-value="gradient"
      compact
      @update:model-value="updateAppearance('background.gradient', $event)"
    />
  </div>
</template>

<style scoped>
.bg-panel {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
}

.bg-panel__heading {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #1f2937;
}

/* ── Top row: color, gradient, image ─────────────────────────────────────── */

.bg-panel__row {
  display: grid;
  grid-template-columns: auto auto auto auto 1fr;
  gap: 0.5rem;
  align-items: start;
}

.bg-panel__row-cell {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

/* Make color picker popover break out of the narrow grid cell */
.bg-panel__row-cell :deep(.color-picker__popover) {
  min-width: 14rem;
}

/* ── Gradient swatch (manual trigger in the row) ─────────────────────────── */

.bg-panel__gradient-swatch {
  width: 2rem;
  height: 2rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  cursor: pointer;
  padding: 0;
  background: #fff;
}

.bg-panel__gradient-swatch--empty {
  background: repeating-linear-gradient(
    45deg,
    #f3f4f6,
    #f3f4f6 4px,
    #e5e7eb 4px,
    #e5e7eb 8px
  );
}

.bg-panel__gradient-swatch:hover {
  border-color: #9ca3af;
}

/* ── Hidden file input ───────────────────────────────────────────────────── */

.bg-panel__file-input {
  display: none;
}

/* ── Image thumbnail with delete button ──────────────────────────────────── */

.bg-panel__image-thumb {
  position: relative;
  width: 2rem;
  height: 2rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  overflow: hidden;
  cursor: pointer;
}

.bg-panel__image-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.bg-panel__image-remove {
  position: absolute;
  top: -1px;
  right: -1px;
  width: 0.875rem;
  height: 0.875rem;
  border: none;
  border-radius: 50%;
  background: #ef4444;
  color: #fff;
  font-size: 0.625rem;
  line-height: 0.875rem;
  text-align: center;
  cursor: pointer;
  padding: 0;
  display: none;
}

.bg-panel__image-thumb:hover .bg-panel__image-remove {
  display: block;
}

/* ── Upload tile (no image state) ────────────────────────────────────────── */

.bg-panel__upload-tile {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: 2px dashed #d1d5db;
  border-radius: 0.25rem;
  background: #f9fafb;
  color: #9ca3af;
  font-size: 0.875rem;
  cursor: pointer;
  padding: 0;
}

.bg-panel__upload-tile:hover {
  border-color: var(--c-primary-400, #818cf8);
  color: var(--c-primary-600, #4f46e5);
}

.bg-panel__upload-tile:disabled {
  cursor: wait;
  opacity: 0.6;
}

.bg-panel__upload-icon {
  font-weight: 300;
  font-size: 1rem;
  line-height: 1;
}

.bg-panel__override {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.8125rem;
  color: #374151;
  cursor: pointer;
}

.bg-panel__override input {
  margin: 0;
}

</style>
