<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Widget } from '../../types'
import { useEditorStore } from '../../stores/editor'
import ColorPicker from '../primitives/ColorPicker.vue'
import GradientPicker from '../primitives/GradientPicker.vue'
import NinePointAlignment from '../primitives/NinePointAlignment.vue'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()
const uploading = ref(false)

const backgroundColor = computed(() => props.widget.appearance_config?.background?.color ?? '#ffffff')
const gradient = computed(() => props.widget.appearance_config?.background?.gradient ?? null)
const alignment = computed(() => props.widget.appearance_config?.background?.alignment ?? 'center')
const fit = computed(() => props.widget.appearance_config?.background?.fit ?? 'cover')
const imageUrl = computed(() => props.widget.appearance_image_url ?? null)
const hasImage = computed(() => imageUrl.value !== null)

function updateAppearance(path: string, value: any) {
  store.updateLocalAppearanceConfig(props.widget.id, path, value)
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

    <!-- Color & Gradient -->
    <div class="bg-panel__section">
      <ColorPicker
        :model-value="backgroundColor"
        label="Color"
        @update:model-value="updateAppearance('background.color', $event)"
      />
    </div>

    <div class="bg-panel__section">
      <GradientPicker
        :model-value="gradient"
        label="Gradient"
        @update:model-value="updateAppearance('background.gradient', $event)"
      />
      <p class="inspector-hint">Use opacity sliders on each gradient stop to tint a background image.</p>
    </div>

    <!-- Image -->
    <div class="bg-panel__section">
      <p class="inspector-section-title">Image</p>

      <input
        :id="`bg-upload-${widget.id}`"
        type="file"
        accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
        class="bg-panel__file-input"
        @change="handleImageUpload"
      >

      <div v-if="hasImage" class="bg-panel__image-preview">
        <img :src="imageUrl!" alt="Background image" class="bg-panel__thumbnail" />
        <button type="button" class="bg-panel__remove-link" @click="handleImageRemove">
          Remove image
        </button>
      </div>

      <button
        v-if="!hasImage"
        type="button"
        class="bg-panel__upload-tile"
        :disabled="uploading"
        @click="triggerFileInput"
      >
        <span v-if="uploading">Uploading…</span>
        <span v-else>Drop or click to upload</span>
      </button>

      <button
        v-if="hasImage && !uploading"
        type="button"
        class="bg-panel__replace-btn"
        @click="triggerFileInput"
      >
        Replace image
      </button>

      <div class="bg-panel__image-row">
        <div class="bg-panel__field">
          <label class="inspector-label">Fit</label>
          <select
            :value="fit"
            class="inspector-control"
            :disabled="!hasImage"
            @change="updateAppearance('background.fit', ($event.target as HTMLSelectElement).value)"
          >
            <option value="cover">Cover</option>
            <option value="contain">Contain</option>
          </select>
        </div>

        <div class="bg-panel__field">
          <NinePointAlignment
            :model-value="alignment"
            :disabled="!hasImage"
            label="Alignment"
            @update:model-value="updateAppearance('background.alignment', $event)"
          />
        </div>
      </div>
      <p v-if="!hasImage" class="inspector-hint inspector-hint--italic">Upload an image to set fit and alignment</p>
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

.bg-panel__section {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.bg-panel__file-input {
  display: none;
}

.bg-panel__upload-tile {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 4rem;
  border: 2px dashed #d1d5db;
  border-radius: 0.375rem;
  background: #f9fafb;
  color: #6b7280;
  font-size: 0.75rem;
  cursor: pointer;
}

.bg-panel__upload-tile:hover {
  border-color: var(--c-primary-400, #818cf8);
  color: var(--c-primary-600, #4f46e5);
}

.bg-panel__upload-tile:disabled {
  cursor: wait;
  opacity: 0.6;
}

.bg-panel__image-preview {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.bg-panel__thumbnail {
  width: 100%;
  max-height: 6rem;
  object-fit: cover;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
}

.bg-panel__remove-link {
  background: none;
  border: none;
  padding: 0;
  font-size: 0.6875rem;
  color: #ef4444;
  cursor: pointer;
  text-align: left;
}

.bg-panel__remove-link:hover {
  text-decoration: underline;
}

.bg-panel__replace-btn {
  padding: 0.25rem 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  background: #fff;
  font-size: 0.6875rem;
  color: #4b5563;
  cursor: pointer;
}

.bg-panel__replace-btn:hover {
  border-color: var(--c-primary-400, #818cf8);
  color: var(--c-primary-600, #4f46e5);
}

.bg-panel__image-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
  align-items: start;
}

.bg-panel__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

</style>
