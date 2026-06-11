<script setup lang="ts">
import { ref } from 'vue'

// Shared image-upload core (session 357). Extracted from BackgroundPanel's
// image sub-panel — the richer of the two image fields — so the widget config
// image field and the appearance background image present one identical
// surface: a click-to-replace preview (or a dashed upload block when empty),
// a remove affordance, and a "Browse library" entry. Purely presentational:
// it owns the hidden file input and emits upload/remove/browse; the parent
// owns the actual store actions and the uploading display state.
//
// Background-only controls (Fit / Alignment / use-page-header) stay in
// BackgroundPanel — they are not part of this shared core. Video fields keep
// their own bare file input in ImageUploadField and do not use this control.

const props = withDefaults(
  defineProps<{
    imageUrl?: string | null
    accept?: string
    uploading?: boolean
    /** Disable the Browse button (background uses this for use-page-header). */
    browseDisabled?: boolean
    showBrowse?: boolean
    alt?: string
  }>(),
  {
    imageUrl: null,
    accept: 'image/png,image/jpeg,image/gif,image/webp,image/svg+xml',
    uploading: false,
    browseDisabled: false,
    showBrowse: true,
    alt: '',
  },
)

const emit = defineEmits<{
  upload: [file: File]
  remove: []
  browse: []
}>()

const fileInput = ref<HTMLInputElement | null>(null)

function triggerFileInput() {
  fileInput.value?.click()
}

function handleFileSelect(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) emit('upload', file)
  input.value = ''
}
</script>

<template>
  <div class="image-upload-control">
    <input
      ref="fileInput"
      type="file"
      :accept="accept"
      class="image-upload-control__file-input"
      @change="handleFileSelect"
    >

    <div
      v-if="imageUrl"
      class="image-upload-control__preview"
      @click="triggerFileInput"
    >
      <img :src="imageUrl" :alt="alt">
      <button
        type="button"
        class="image-upload-control__remove"
        title="Remove image"
        @click.stop="emit('remove')"
      >&times;</button>
    </div>
    <button
      v-else
      type="button"
      class="image-upload-control__upload-block"
      :disabled="uploading"
      @click="triggerFileInput"
    >
      <span v-if="uploading">Uploading…</span>
      <span v-else>Click to upload an image</span>
    </button>

    <button
      v-if="showBrowse"
      type="button"
      class="image-upload-control__browse"
      :disabled="browseDisabled"
      @click="emit('browse')"
    >Browse library</button>
  </div>
</template>

<style scoped>
.image-upload-control {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}

.image-upload-control__file-input {
  display: none;
}

/* ── Preview (click to replace) ──────────────────────────────────────────── */

.image-upload-control__preview {
  position: relative;
  width: 100%;
  min-height: 6rem;
  max-height: 10rem;
  border: 1px solid var(--np-control-border);
  border-radius: var(--np-control-radius);
  overflow: hidden;
  cursor: pointer;
}

.image-upload-control__preview img {
  width: 100%;
  height: 100%;
  max-height: 10rem;
  object-fit: cover;
  display: block;
}

.image-upload-control__remove {
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

/* ── Upload block (shown when empty) ─────────────────────────────────────── */

.image-upload-control__upload-block {
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

.image-upload-control__upload-block:hover {
  border-color: var(--c-primary-400, #818cf8);
  color: var(--c-primary-600, #4f46e5);
}

.image-upload-control__upload-block:disabled {
  cursor: wait;
  opacity: 0.6;
}

/* ── Browse library button ───────────────────────────────────────────────── */

.image-upload-control__browse {
  align-self: flex-start;
  border: 1px solid var(--np-control-border);
  border-radius: var(--np-control-radius);
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
  background: var(--np-control-chip-bg);
  color: var(--np-control-icon-default);
  cursor: pointer;
}

.image-upload-control__browse:hover:not(:disabled) {
  border-color: var(--c-primary-400, #818cf8);
  color: var(--c-primary-600, #4f46e5);
}

.image-upload-control__browse:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
</style>
