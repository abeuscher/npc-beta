<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { useEditorStore } from '../stores/editor'

const store = useEditorStore()

const visible = computed(() => store.dedupPrompt !== null)
const matches = computed(() => store.dedupPrompt?.matches ?? [])

// The first candidate is the headline reuse target (referenced-first, then
// most-recent). Identical-byte matches collapse to one row carrying a count.
const headline = computed(() => matches.value[0] ?? null)
const isIdentical = computed(() => headline.value?.match_type === 'identical')

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function useExisting(mediaId: number) {
  store.resolveDedup({ type: 'use-existing', mediaId })
}

function keepNew() {
  store.resolveDedup({ type: 'keep-new' })
}

function cancel() {
  store.resolveDedup({ type: 'cancel' })
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && visible.value) cancel()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="visible" class="dedup-overlay" @click.self="cancel">
        <div class="dedup-modal">
          <p class="dedup-modal__title">
            {{ isIdentical ? 'You already have this image' : 'A file with this name already exists' }}
          </p>
          <p class="dedup-modal__subtitle">
            {{ isIdentical
              ? 'This exact image is already in your media library. Reuse it instead of uploading another copy?'
              : 'Pick the existing asset to reuse, or upload this as a new image.' }}
          </p>

          <ul class="dedup-modal__list">
            <li v-for="match in matches" :key="match.id" class="dedup-modal__item">
              <div class="dedup-modal__thumb">
                <img v-if="match.url" :src="match.url" :alt="match.file_name" />
                <span v-else class="dedup-modal__thumb-fallback">{{ match.file_name }}</span>
              </div>
              <div class="dedup-modal__meta">
                <span class="dedup-modal__name">{{ match.file_name }}</span>
                <span class="dedup-modal__detail">
                  {{ formatSize(match.size) }}
                  <template v-if="match.referenced"> · in use on your site</template>
                  <template v-if="match.duplicate_count > 1"> · {{ match.duplicate_count }} copies</template>
                </span>
              </div>
              <button
                type="button"
                class="dedup-modal__btn dedup-modal__btn--use"
                @click="useExisting(match.id)"
              >Use this</button>
            </li>
          </ul>

          <div class="dedup-modal__actions">
            <button type="button" class="dedup-modal__btn dedup-modal__btn--cancel" @click="cancel">Cancel</button>
            <button type="button" class="dedup-modal__btn dedup-modal__btn--new" @click="keepNew">Upload as new</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.dedup-overlay {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.5);
}

.dedup-modal {
  width: 100%;
  max-width: 28rem;
  border-radius: 0.75rem;
  background: #fff;
  padding: 1.5rem;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
}

.dedup-modal__title {
  margin: 0 0 0.375rem;
  font-size: 1rem;
  font-weight: 600;
  color: #1f2937;
}

.dedup-modal__subtitle {
  margin: 0 0 1rem;
  font-size: 0.8125rem;
  color: #6b7280;
  line-height: 1.45;
}

.dedup-modal__list {
  list-style: none;
  margin: 0 0 1.25rem;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-height: 16rem;
  overflow-y: auto;
}

.dedup-modal__item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
}

.dedup-modal__thumb {
  flex: 0 0 auto;
  width: 3rem;
  height: 3rem;
  border-radius: 0.375rem;
  overflow: hidden;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
}

.dedup-modal__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.dedup-modal__thumb-fallback {
  font-size: 0.5rem;
  color: #9ca3af;
  padding: 0.25rem;
  text-align: center;
  word-break: break-all;
}

.dedup-modal__meta {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.dedup-modal__name {
  font-size: 0.8125rem;
  font-weight: 500;
  color: #374151;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dedup-modal__detail {
  font-size: 0.75rem;
  color: #9ca3af;
}

.dedup-modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.dedup-modal__btn {
  border: none;
  border-radius: 0.5rem;
  padding: 0.5rem 1rem;
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
}

.dedup-modal__btn--use {
  flex: 0 0 auto;
  background: var(--c-primary-600, #4f46e5);
  color: #fff;
  padding: 0.375rem 0.75rem;
}

.dedup-modal__btn--use:hover {
  background: var(--c-primary-500, #6366f1);
}

.dedup-modal__btn--cancel {
  background: none;
  color: #6b7280;
}

.dedup-modal__btn--cancel:hover {
  background: #f3f4f6;
}

.dedup-modal__btn--new {
  background: #f3f4f6;
  color: #374151;
}

.dedup-modal__btn--new:hover {
  background: #e5e7eb;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.15s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

html.dark .dedup-modal { background: rgb(31 41 55); }
html.dark .dedup-modal__title { color: rgb(229 231 235); }
html.dark .dedup-modal__item { border-color: rgb(75 85 99); }
html.dark .dedup-modal__name { color: rgb(209 213 219); }
html.dark .dedup-modal__btn--new { background: rgb(55 65 81); color: rgb(209 213 219); }
</style>
