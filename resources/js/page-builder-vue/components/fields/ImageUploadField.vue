<script setup lang="ts">
import { ref, computed } from 'vue'
import type { FieldDef, Widget } from '../../types'
import { useEditorStore } from '../../stores/editor'
import ImageUploadControl from '../primitives/ImageUploadControl.vue'

const props = defineProps<{
  field: FieldDef
  widget: Widget
  modelValue: any
}>()

const store = useEditorStore()
const uploading = ref(false)

const isVideo = computed(() => props.field.type === 'video')

const currentUrl = computed(() => props.widget.image_urls?.[props.field.key] ?? null)

// Shared with the video bare path below; the image path uses ImageUploadControl,
// which emits the File directly.
async function uploadFile(file: File) {
  uploading.value = true
  try {
    await store.uploadImage(props.widget.id, props.field.key, file)
  } finally {
    uploading.value = false
  }
}

async function handleVideoFileSelect(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  try {
    await uploadFile(file)
  } finally {
    input.value = ''
  }
}

async function handleRemove() {
  await store.removeImage(props.widget.id, props.field.key)
}

function openBrowser() {
  store.openMediaBrowser({ kind: 'config', widgetId: props.widget.id, key: props.field.key })
}
</script>

<template>
  <div class="image-upload">
    <!-- Image fields: the shared upload control (converged on the background
         model at session 357 — preview / upload-block / remove / Browse). -->
    <ImageUploadControl
      v-if="!isVideo"
      :image-url="currentUrl"
      :uploading="uploading"
      @upload="uploadFile"
      @remove="handleRemove"
      @browse="openBrowser"
    />

    <!-- Video fields keep their bare file input (no Browse), unchanged. -->
    <template v-else>
      <div v-if="currentUrl" class="image-upload__preview">
        <video
          :src="currentUrl"
          class="image-upload__media"
          muted
          playsinline
        />
        <button
          type="button"
          class="image-upload__remove"
          title="Remove"
          @click="handleRemove"
        >&times;</button>
      </div>

      <div class="image-upload__actions">
        <input
          type="file"
          accept="video/mp4,video/webm"
          class="image-upload__input"
          @change="handleVideoFileSelect"
        >
      </div>

      <p v-if="uploading" class="image-upload__status">Uploading…</p>
    </template>
  </div>
</template>

<style scoped>
.image-upload {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.image-upload__preview {
  position: relative;
  display: inline-block;
}

.image-upload__media {
  max-height: 8rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.25rem;
}

.image-upload__remove {
  position: absolute;
  top: -0.375rem;
  right: -0.375rem;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: 50%;
  border: none;
  background: #ef4444;
  color: #fff;
  font-size: 0.75rem;
  line-height: 1.25rem;
  text-align: center;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.image-upload__remove:hover {
  background: #dc2626;
}

.image-upload__actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.image-upload__input {
  font-size: 0.875rem;
  color: #4b5563;
}

.image-upload__status {
  margin: 0;
  font-size: 0.75rem;
  color: var(--c-primary-600, #4f46e5);
}

html.dark .image-upload          { background: rgb(31 41 55); border-color: rgb(75 85 99); color: rgb(156 163 175); }
html.dark .image-upload__preview { border-color: rgb(75 85 99); }
html.dark .image-upload__status  { color: rgb(156 163 175); }
html.dark .image-upload__remove  { color: rgb(248 113 113); }
</style>
