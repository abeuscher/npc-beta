<script setup lang="ts">
import { computed, ref } from 'vue'
import ColorPicker from '../primitives/ColorPicker.vue'
import GradientPicker from '../primitives/GradientPicker.vue'
import NinePointAlignment from '../primitives/NinePointAlignment.vue'
import { composeGradientCss } from '../../helpers/gradient'

type BgTab = 'color' | 'gradient' | 'image' | null

const props = withDefaults(
  defineProps<{
    config: Record<string, any>
    imageUrl?: string | null
    idPrefix: string
    showImage?: boolean
  }>(),
  {
    imageUrl: null,
    showImage: true,
  },
)

const emit = defineEmits<{
  update: [path: string, value: any]
  uploadImage: [file: File]
  removeImage: []
}>()

const uploading = ref(false)

const backgroundColor = computed(() => props.config?.background?.color ?? '')
const hasColor = computed(() => !!backgroundColor.value)
const colorSwatchStyle = computed(() =>
  hasColor.value ? { backgroundColor: backgroundColor.value } : undefined
)
const gradient = computed(() => props.config?.background?.gradient ?? null)
const alignment = computed(() => props.config?.background?.alignment ?? 'center')
const fit = computed(() => props.config?.background?.fit ?? 'cover')
const useCurrentPageHeader = computed(() => props.config?.background?.use_current_page_header ?? false)
const hasImage = computed(() => props.imageUrl !== null && props.imageUrl !== '')
const imageControlsDisabled = computed(() => useCurrentPageHeader.value || !hasImage.value)

const hasGradient = computed(() => Array.isArray(gradient.value?.gradients) && gradient.value!.gradients.length > 0)
const gradientPreviewCss = computed(() => composeGradientCss(gradient.value ?? null))
const gradientSwatchStyle = computed(() => {
  if (!hasGradient.value || gradientPreviewCss.value === '') return undefined
  return { backgroundImage: gradientPreviewCss.value }
})
const imageSwatchStyle = computed(() =>
  hasImage.value ? { backgroundImage: `url(${props.imageUrl})` } : undefined
)

function initialTab(): BgTab {
  if (props.showImage && hasImage.value) return 'image'
  if (hasGradient.value) return 'gradient'
  return 'color'
}
const openPanel = ref<BgTab>(initialTab())

function togglePanel(panel: Exclude<BgTab, null>) {
  openPanel.value = openPanel.value === panel ? null : panel
}

function update(path: string, value: any) {
  emit('update', path, value)
}

async function handleImageUpload(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  uploading.value = true
  try {
    emit('uploadImage', file)
  } finally {
    uploading.value = false
    input.value = ''
  }
}

function handleImageRemove() {
  emit('removeImage')
}

function triggerFileInput() {
  const input = document.getElementById(`bg-upload-${props.idPrefix}`) as HTMLInputElement | null
  input?.click()
}
</script>

<template>
  <div class="bg-panel">
    <p class="bg-panel__heading">Background</p>

    <!-- Handle row: two or three tabs (Color / Gradient [/ Image]) -->
    <div class="bg-panel__row">
      <div class="bg-panel__row-cell">
        <label class="inspector-label">Color</label>
        <button
          type="button"
          class="bg-panel__swatch bg-panel__swatch--color"
          :class="{
            'bg-panel__swatch--empty': !hasColor,
            'bg-panel__swatch--active': openPanel === 'color',
          }"
          :style="colorSwatchStyle"
          @click.stop="togglePanel('color')"
        />
      </div>

      <div class="bg-panel__row-cell">
        <label class="inspector-label">Gradient</label>
        <button
          type="button"
          class="bg-panel__swatch bg-panel__swatch--gradient"
          :class="{
            'bg-panel__swatch--empty': !hasGradient,
            'bg-panel__swatch--active': openPanel === 'gradient',
          }"
          :style="gradientSwatchStyle"
          @click.stop="togglePanel('gradient')"
        />
      </div>

      <div v-if="showImage" class="bg-panel__row-cell">
        <label class="inspector-label">Image</label>
        <button
          type="button"
          class="bg-panel__swatch bg-panel__swatch--image"
          :class="{
            'bg-panel__swatch--empty': !hasImage,
            'bg-panel__swatch--active': openPanel === 'image',
          }"
          :style="imageSwatchStyle"
          @click.stop="togglePanel('image')"
        >
          <span v-if="!hasImage" class="bg-panel__swatch-icon">+</span>
        </button>
      </div>
    </div>

    <!-- Color panel -->
    <ColorPicker
      v-if="openPanel === 'color'"
      :model-value="backgroundColor"
      panel-only
      @update:model-value="update('color', $event)"
    />

    <!-- Gradient panel -->
    <GradientPicker
      v-if="openPanel === 'gradient'"
      :model-value="gradient"
      compact
      @update:model-value="update('gradient', $event)"
    />

    <!-- Image panel -->
    <div v-if="showImage && openPanel === 'image'" class="bg-panel__image-panel">
      <div class="bg-panel__image-row">
        <div class="bg-panel__image-row-cell">
          <label class="inspector-label">Fit</label>
          <select
            :value="fit"
            class="inspector-control inspector-control--sm"
            :disabled="imageControlsDisabled"
            @change="update('fit', ($event.target as HTMLSelectElement).value)"
          >
            <option value="cover">Cover</option>
            <option value="contain">Contain</option>
          </select>
        </div>
        <div class="bg-panel__image-row-cell">
          <label class="inspector-label">Alignment</label>
          <NinePointAlignment
            :model-value="alignment"
            :disabled="imageControlsDisabled"
            @update:model-value="update('alignment', $event)"
          />
        </div>
      </div>

      <label class="bg-panel__override">
        <input
          type="checkbox"
          :checked="useCurrentPageHeader"
          @change="update('use_current_page_header', ($event.target as HTMLInputElement).checked)"
        >
        <span>Use current page's header image</span>
      </label>

      <input
        :id="`bg-upload-${idPrefix}`"
        type="file"
        accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
        class="bg-panel__file-input"
        @change="handleImageUpload"
      >

      <div v-if="hasImage" class="bg-panel__image-preview" @click="triggerFileInput">
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
        class="bg-panel__upload-block"
        :disabled="uploading"
        @click="triggerFileInput"
      >
        <span v-if="uploading">Uploading…</span>
        <span v-else>Click to upload an image</span>
      </button>
    </div>
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

/* ── Handle row ──────────────────────────────────────────────────────────── */

.bg-panel__row {
  display: grid;
  grid-template-columns: repeat(3, auto);
  gap: 0.5rem;
  align-items: start;
}

.bg-panel__row-cell {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

/* ── Swatches (Color / Gradient / Image handles) ─────────────────────────── */

.bg-panel__swatch {
  position: relative;
  width: 2rem;
  height: 2rem;
  border: 1px solid var(--np-control-border);
  border-radius: var(--np-control-radius);
  cursor: pointer;
  padding: 0;
  background: var(--np-control-chip-bg);
  transition: var(--np-control-transition);
  background-size: cover;
  background-position: center;
}

.bg-panel__swatch--empty {
  background: repeating-linear-gradient(
    45deg,
    #f3f4f6,
    #f3f4f6 4px,
    #e5e7eb 4px,
    #e5e7eb 8px
  );
}

.bg-panel__swatch:hover {
  border-color: var(--np-control-border-hover);
}

/* Active state — connects visually to the open panel below. */
.bg-panel__swatch--active {
  border-color: var(--np-control-border-active);
  box-shadow: 0 0 0 1px var(--np-control-border-active);
}

.bg-panel__swatch-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  font-size: 1rem;
  font-weight: 300;
  color: var(--np-control-icon-default);
  line-height: 1;
}

/* ── Image panel body ────────────────────────────────────────────────────── */

.bg-panel__image-panel {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  margin-top: 0.5rem;
  padding: 0.75rem;
  border: 1px solid var(--np-control-border);
  border-radius: var(--np-control-radius);
  background: var(--np-control-group-bg);
}

.bg-panel__image-row {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 0.75rem;
  align-items: start;
}

.bg-panel__image-row-cell {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

/* ── Override checkbox (moved into Image panel) ──────────────────────────── */

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

/* ── Hidden file input ───────────────────────────────────────────────────── */

.bg-panel__file-input {
  display: none;
}

/* ── Image preview inside Image panel ────────────────────────────────────── */

.bg-panel__image-preview {
  position: relative;
  width: 100%;
  min-height: 6rem;
  max-height: 10rem;
  border: 1px solid var(--np-control-border);
  border-radius: var(--np-control-radius);
  overflow: hidden;
  cursor: pointer;
}

.bg-panel__image-preview img {
  width: 100%;
  height: 100%;
  max-height: 10rem;
  object-fit: cover;
  display: block;
}

.bg-panel__image-remove {
  position: absolute;
  top: 0.375rem;
  right: 0.375rem;
  width: 1.25rem;
  height: 1.25rem;
  border: none;
  border-radius: 50%;
  background: #ef4444;
  color: #fff;
  font-size: 0.875rem;
  line-height: 1.25rem;
  text-align: center;
  cursor: pointer;
  padding: 0;
}

/* ── Upload block (full-width button in Image panel) ─────────────────────── */

.bg-panel__upload-block {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 4rem;
  border: 2px dashed var(--np-control-border);
  border-radius: var(--np-control-radius);
  background: var(--np-control-chip-bg);
  color: var(--np-control-icon-default);
  font-size: 0.8125rem;
  cursor: pointer;
  padding: 0.75rem;
}

.bg-panel__upload-block:hover {
  border-color: var(--c-primary-400, #818cf8);
  color: var(--c-primary-600, #4f46e5);
}

.bg-panel__upload-block:disabled {
  cursor: wait;
  opacity: 0.6;
}

html.dark .bg-panel__heading  { color: rgb(229 231 235); }
html.dark .bg-panel__override { color: rgb(209 213 219); }
</style>
