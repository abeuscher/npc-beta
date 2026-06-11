<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { useEditorStore } from '../stores/editor'
import type { MediaBrowserItem } from '../types'

const store = useEditorStore()

const visible = computed(() => store.mediaBrowser !== null)
const target = computed(() => store.mediaBrowser?.target ?? null)

const search = ref('')
const items = ref<MediaBrowserItem[]>([])
const page = ref(1)
const hasMore = ref(false)
const loading = ref(false)
const error = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

// Images only this session — video fields don't get a Browse entry.
const acceptTypes = 'image/png,image/jpeg,image/gif,image/webp,image/svg+xml'

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

async function fetchPage(reset: boolean) {
  if (loading.value) return
  loading.value = true
  error.value = false
  try {
    const res = await store.requireApi().listMedia({
      search: search.value || undefined,
      page: page.value,
    })
    items.value = reset ? res.data : [...items.value, ...res.data]
    hasMore.value = res.has_more
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

function loadMore() {
  if (!hasMore.value || loading.value) return
  page.value += 1
  fetchPage(false)
}

const runSearch = useDebounceFn(() => {
  page.value = 1
  fetchPage(true)
}, 300)

watch(search, () => {
  if (visible.value) runSearch()
})

// Fetch on open; reset on close so the next open starts clean. Resetting on
// close (not open) keeps the search at '' between opens, so the open fetch
// isn't shadowed by a second search-triggered fetch.
watch(visible, (now) => {
  if (now) {
    page.value = 1
    fetchPage(true)
  } else {
    search.value = ''
    items.value = []
    hasMore.value = false
    error.value = false
  }
})

async function select(mediaId: number) {
  const t = target.value
  if (!t) return
  await store.useExistingMedia(t, mediaId)
  store.closeMediaBrowser()
}

function triggerUpload() {
  fileInput.value?.click()
}

async function onFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  const t = target.value
  if (!t) return

  // Close first so the post-upload dedup prompt (if the bytes match an existing
  // asset) layers cleanly. Upload runs the existing demo-gated upload path.
  store.closeMediaBrowser()
  if (t.kind === 'appearance') {
    await store.uploadAppearanceImage(t.widgetId, file)
  } else {
    await store.uploadImage(t.widgetId, t.key, file)
  }
}

function close() {
  store.closeMediaBrowser()
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && visible.value) close()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="visible" class="media-browser-overlay" @click.self="close">
        <div class="media-browser">
          <header class="media-browser__header">
            <p class="media-browser__title">Media library</p>
            <button type="button" class="media-browser__close" title="Close" @click="close">&times;</button>
          </header>

          <div class="media-browser__toolbar">
            <input
              v-model="search"
              type="search"
              class="media-browser__search"
              placeholder="Search by file name…"
            >
            <button type="button" class="media-browser__upload-btn" @click="triggerUpload">
              Upload new
            </button>
            <input
              ref="fileInput"
              type="file"
              :accept="acceptTypes"
              class="media-browser__file-input"
              @change="onFile"
            >
          </div>

          <div class="media-browser__body">
            <p v-if="error" class="media-browser__message">
              Could not load media. Please try again.
            </p>
            <p v-else-if="!loading && items.length === 0" class="media-browser__message">
              {{ search ? 'No images match your search.' : 'No images yet — upload one to get started.' }}
            </p>

            <ul v-else class="media-browser__grid">
              <li v-for="item in items" :key="item.media_id" class="media-browser__cell">
                <button
                  type="button"
                  class="media-browser__tile"
                  :title="item.file_name"
                  @click="select(item.media_id)"
                >
                  <img
                    v-if="item.url"
                    :src="item.url"
                    :alt="item.file_name"
                    class="media-browser__thumb"
                    loading="lazy"
                  >
                  <span v-else class="media-browser__thumb-fallback">{{ item.file_name }}</span>
                  <span class="media-browser__meta">
                    <span class="media-browser__name">{{ item.file_name }}</span>
                    <span class="media-browser__size">{{ formatSize(item.size) }}</span>
                  </span>
                </button>
              </li>
            </ul>

            <div v-if="loading" class="media-browser__message">Loading…</div>

            <div v-if="hasMore && !loading" class="media-browser__more">
              <button type="button" class="media-browser__more-btn" @click="loadMore">Load more</button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.media-browser-overlay {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.5);
  padding: 1.5rem;
}

.media-browser {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 52rem;
  max-height: 85vh;
  border-radius: 0.75rem;
  background: #fff;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
}

.media-browser__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #e5e7eb;
}

.media-browser__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: #1f2937;
}

.media-browser__close {
  border: none;
  background: none;
  font-size: 1.5rem;
  line-height: 1;
  color: #9ca3af;
  cursor: pointer;
  padding: 0 0.25rem;
}

.media-browser__close:hover {
  color: #4b5563;
}

.media-browser__toolbar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1.25rem;
  border-bottom: 1px solid #e5e7eb;
}

.media-browser__search {
  flex: 1 1 auto;
  min-width: 0;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  color: #1f2937;
}

.media-browser__search:focus {
  outline: none;
  border-color: var(--c-primary-500, #6366f1);
  box-shadow: 0 0 0 1px var(--c-primary-500, #6366f1);
}

.media-browser__upload-btn {
  flex: 0 0 auto;
  border: none;
  border-radius: 0.5rem;
  padding: 0.5rem 1rem;
  font-size: 0.8125rem;
  font-weight: 500;
  background: var(--c-primary-600, #4f46e5);
  color: #fff;
  cursor: pointer;
}

.media-browser__upload-btn:hover {
  background: var(--c-primary-500, #6366f1);
}

.media-browser__file-input {
  display: none;
}

.media-browser__body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  padding: 1.25rem;
}

.media-browser__message {
  padding: 2rem 1rem;
  text-align: center;
  font-size: 0.875rem;
  color: #6b7280;
}

.media-browser__grid {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr));
  gap: 0.875rem;
}

.media-browser__tile {
  display: flex;
  flex-direction: column;
  width: 100%;
  padding: 0;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  background: #fff;
  cursor: pointer;
  overflow: hidden;
  transition: border-color 0.12s ease, box-shadow 0.12s ease;
}

.media-browser__tile:hover {
  border-color: var(--c-primary-400, #818cf8);
  box-shadow: 0 0 0 1px var(--c-primary-400, #818cf8);
}

.media-browser__thumb {
  width: 100%;
  aspect-ratio: 1 / 1;
  object-fit: cover;
  display: block;
  background: #f3f4f6;
}

.media-browser__thumb-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  aspect-ratio: 1 / 1;
  background: #f3f4f6;
  color: #9ca3af;
  font-size: 0.625rem;
  padding: 0.5rem;
  text-align: center;
  word-break: break-all;
}

.media-browser__meta {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  padding: 0.375rem 0.5rem;
  text-align: left;
  border-top: 1px solid #f3f4f6;
}

.media-browser__name {
  font-size: 0.6875rem;
  color: #374151;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.media-browser__size {
  font-size: 0.625rem;
  color: #9ca3af;
}

.media-browser__more {
  display: flex;
  justify-content: center;
  margin-top: 1.25rem;
}

.media-browser__more-btn {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  padding: 0.5rem 1.25rem;
  font-size: 0.8125rem;
  background: #f9fafb;
  color: #374151;
  cursor: pointer;
}

.media-browser__more-btn:hover {
  background: #f3f4f6;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.15s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

html.dark .media-browser { background: rgb(31 41 55); }
html.dark .media-browser__header,
html.dark .media-browser__toolbar { border-color: rgb(55 65 81); }
html.dark .media-browser__title { color: rgb(229 231 235); }
html.dark .media-browser__search {
  background: rgb(17 24 39);
  border-color: rgb(75 85 99);
  color: rgb(229 231 235);
}
html.dark .media-browser__tile { background: rgb(17 24 39); border-color: rgb(55 65 81); }
html.dark .media-browser__meta { border-color: rgb(55 65 81); }
html.dark .media-browser__name { color: rgb(209 213 219); }
html.dark .media-browser__more-btn { background: rgb(55 65 81); border-color: rgb(75 85 99); color: rgb(209 213 219); }
</style>
