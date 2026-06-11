<script setup lang="ts">
import { ref, computed } from 'vue'
import type { FieldDef, Widget } from '../../types'
import { useEditorStore } from '../../stores/editor'

const props = defineProps<{
  field: FieldDef
  widget: Widget
  modelValue: any
}>()

const store = useEditorStore()
const uploading = ref(false)

const isVideo = computed(() => props.field.type === 'video')

const acceptTypes = computed(() =>
  isVideo.value
    ? 'video/mp4,video/webm'
    : 'image/png,image/jpeg,image/gif,image/webp,image/svg+xml'
)

const currentUrl = computed(() => props.widget.image_urls?.[props.field.key] ?? null)

async function handleFileSelect(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  uploading.value = true
  try {
    await store.uploadImage(props.widget.id, props.field.key, file)
  } finally {
    uploading.value = false
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
    <div v-if="currentUrl" class="image-upload__preview">
      <video
        v-if="isVideo"
        :src="currentUrl"
        class="image-upload__media"
        muted
        playsinline
      />
      <img
        v-else
        :src="currentUrl"
        alt=""
        class="image-upload__media"
      >
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
        :accept="acceptTypes"
        class="image-upload__input"
        @change="handleFileSelect"
      >
      <button
        v-if="!isVideo"
        type="button"
        class="image-upload__browse"
        @click="openBrowser"
      >Browse library</button>
    </div>

    <p v-if="uploading" class="image-upload__status">Uploading…</p>
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

.image-upload__browse {
  flex: 0 0 auto;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  padding: 0.3125rem 0.75rem;
  font-size: 0.8125rem;
  background: #f9fafb;
  color: #374151;
  cursor: pointer;
}

.image-upload__browse:hover {
  background: #f3f4f6;
  border-color: var(--c-primary-400, #818cf8);
  color: var(--c-primary-600, #4f46e5);
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
html.dark .image-upload__browse  { background: rgb(55 65 81); border-color: rgb(75 85 99); color: rgb(209 213 219); }
</style>
