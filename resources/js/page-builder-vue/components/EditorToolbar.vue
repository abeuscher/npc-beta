<script setup lang="ts">
import { useEditorStore } from '../stores/editor'

const store = useEditorStore()
</script>

<template>
  <div class="editor-toolbar">
    <div class="editor-toolbar__left">
      <div class="editor-toolbar__row1">
        <span class="editor-toolbar__title">{{ store.pageTitle }}</span>
        <span v-if="store.pageAuthor" class="editor-toolbar__author">by {{ store.pageAuthor }}</span>
        <span class="editor-toolbar__status">{{ store.pageStatus }}</span>
      </div>
      <div class="editor-toolbar__row2">
        <a
          v-if="store.pageUrl"
          :href="store.pageStatus === 'published' ? store.pageUrl : undefined"
          :target="store.pageStatus === 'published' ? '_blank' : undefined"
          class="editor-toolbar__url"
          :class="{ 'editor-toolbar__url--draft': store.pageStatus !== 'published' }"
          :title="store.pageStatus === 'published' ? store.pageUrl : 'Page not published'"
        >{{ store.pageUrl }}</a>
        <span
          v-for="tag in store.pageTags"
          :key="tag"
          class="editor-toolbar__tag"
        >{{ tag }}</span>
      </div>
    </div>

    <div class="editor-toolbar__right"></div>
  </div>
</template>

<style scoped>
.editor-toolbar {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0;
  border: none;
}

.editor-toolbar__left {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.editor-toolbar__row1 {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.editor-toolbar__title {
  font-size: 1.125rem;
  font-weight: 700;
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 30vw;
}

.editor-toolbar__author {
  font-size: 0.875rem;
  color: #6b7280;
}

.editor-toolbar__status {
  font-size: 0.8125rem;
  font-style: italic;
  color: #9ca3af;
}

.editor-toolbar__row2 {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.editor-toolbar__url {
  font-size: 0.8125rem;
  font-family: monospace;
  color: var(--c-primary-600, #4f46e5);
  text-decoration: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 40vw;
}

.editor-toolbar__url:hover {
  text-decoration: underline;
}

.editor-toolbar__url--draft {
  color: #9ca3af;
  pointer-events: none;
  cursor: default;
}

.editor-toolbar__tag {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem 0.5rem;
  font-size: 0.6875rem;
  font-weight: 500;
  color: #4b5563;
  background: #f3f4f6;
  border-radius: 9999px;
  white-space: nowrap;
}

.editor-toolbar__right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}
</style>
