<script setup lang="ts">
import type { Widget } from '../types'
import { useEditorStore } from '../stores/editor'
import { computed, watch, ref, toRef } from 'vue'
import { useInlineEdit } from '../composables/useInlineEdit'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()

const isSelected = computed(() => store.selectedBlockId === props.widget.id)

const htmlEl = ref<HTMLElement | null>(null)

// Armed = this widget is selected AND code-declared inline-eligible. Only
// then do the annotated prose nodes become interactive (overlay yields
// pointer events); the unselected preview stays pixel-clean.
const inlineArmed = computed(
  () => isSelected.value && !!props.widget.widget_type_inline_editable,
)

useInlineEdit(htmlEl, toRef(props, 'widget'), isSelected)

const indicatorStage = computed(() => store.widgetIndicatorStage(props.widget.id))
const previewError = computed(() => store.widgetPreviewError(props.widget.id))
const truncatedError = computed(() => {
  const msg = previewError.value
  if (!msg) return ''
  return msg.length > 80 ? msg.slice(0, 80) + '…' : msg
})

const needsConfig = computed(() => {
  const req = props.widget.widget_type_required_config
  if (!req?.keys?.length) return false
  return req.keys.some(key => {
    const val = props.widget.config[key] ?? props.widget.resolved_defaults?.[key]
    return val === null || val === undefined || val === ''
  })
})

watch(needsConfig, (now, was) => {
  if (was && !now) {
    store.refreshPreview(props.widget.id)
  }
})

function handleClick() {
  store.selectBlock(props.widget.id)
}

function handleEdit() {
  store.selectBlock(props.widget.id)
  store.inspectorTopTab = 'content'
}
</script>

<template>
  <div
    class="preview-region"
    :class="{
      'preview-region--selected': isSelected,
      'preview-region--refreshing-blur': indicatorStage >= 1,
      'preview-region--refreshing-spinner': indicatorStage >= 2,
      'preview-region--inline-armed': inlineArmed,
    }"
    :aria-busy="indicatorStage >= 1 ? 'true' : undefined"
    :data-widget-id="widget.id"
    :data-widget-label="widget.label || widget.widget_type_label"
  >
    <template v-if="needsConfig">
      <div class="widget-preview-notice">
        <strong class="widget-preview-notice__label">{{ widget.widget_type_label }}</strong>
        <span class="widget-preview-notice__message">{{ widget.widget_type_required_config!.message }}</span>
      </div>
    </template>
    <template v-else>
      <div ref="htmlEl" class="preview-region__html" v-html="widget.preview_html"></div>
    </template>
    <div class="preview-region__overlay" @click.stop="handleClick">
      <div
        v-if="indicatorStage >= 2"
        class="preview-region__spinner"
        aria-hidden="true"
      ></div>
      <div
        v-if="previewError"
        class="preview-region__error-badge"
        :title="previewError"
      >{{ truncatedError }}</div>
    </div>

    <div
      class="preview-region__handle"
      role="button"
      tabindex="-1"
      title="Drag to reorder"
      aria-label="Drag to reorder"
    >
      <svg viewBox="0 0 20 20" width="14" height="14" aria-hidden="true">
        <circle cx="7" cy="5" r="1.5" />
        <circle cx="13" cy="5" r="1.5" />
        <circle cx="7" cy="10" r="1.5" />
        <circle cx="13" cy="10" r="1.5" />
        <circle cx="7" cy="15" r="1.5" />
        <circle cx="13" cy="15" r="1.5" />
      </svg>
    </div>

    <button
      type="button"
      class="preview-region__edit"
      title="Edit in the Inspector"
      aria-label="Edit in the Inspector"
      @click.stop="handleEdit"
    >
      <svg viewBox="0 0 20 20" width="13" height="13" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
        <path d="M13.5 3.5 16.5 6.5 7 16 4 16 4 13 13.5 3.5Z" />
      </svg>
      <span>Edit</span>
    </button>
  </div>
</template>

<style scoped>
.preview-region {
  position: relative;
}

.preview-region__html {
  pointer-events: none;
}

.preview-region__overlay {
  position: absolute;
  inset: 0;
  pointer-events: auto;
  cursor: pointer;
  transition: box-shadow 0.15s, backdrop-filter 0.1s;
}

.preview-region--selected > .preview-region__overlay {
  outline: 2px solid #6366f1;
  outline-offset: -1px;
}

/* When the selected widget is inline-eligible, the overlay yields pointer
   events so the armed prose nodes inside the (otherwise inert) preview
   HTML are directly clickable. The selected outline still paints. */
.preview-region--inline-armed > .preview-region__overlay {
  pointer-events: none;
}

.preview-region--inline-armed .preview-region__html :deep(.inline-editable) {
  pointer-events: auto;
}

.preview-region--refreshing-blur > .preview-region__overlay {
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
}

/* ── Hover-in affordances: drag grip (top-left) + Edit (top-right) ─────────── */

.preview-region__handle,
.preview-region__edit {
  position: absolute;
  top: 0.375rem;
  z-index: 3;
  display: inline-flex;
  align-items: center;
  pointer-events: auto;
  opacity: 0;
  transition: opacity 0.15s ease, transform 0.15s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.18);
}

.preview-region__handle {
  left: 0.375rem;
  justify-content: center;
  width: 1.625rem;
  height: 1.625rem;
  color: #fff;
  background: #4f46e5;
  border-radius: 0.3125rem;
  cursor: grab;
  transform: translateX(-4px);
}

.preview-region__handle:active {
  cursor: grabbing;
}

.preview-region__handle svg {
  fill: currentColor;
}

.preview-region__edit {
  right: 0.375rem;
  gap: 0.25rem;
  padding: 0.25rem 0.5rem;
  font-size: 0.6875rem;
  font-weight: 600;
  color: #374151;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 0.3125rem;
  cursor: pointer;
  transform: translateX(4px);
}

.preview-region__edit:hover {
  color: var(--c-primary-700, #4338ca);
  border-color: var(--c-primary-300, #a5b4fc);
}

.preview-region:hover > .preview-region__handle,
.preview-region:hover > .preview-region__edit,
.preview-region--selected > .preview-region__handle,
.preview-region--selected > .preview-region__edit {
  opacity: 1;
  transform: translateX(0);
}

.preview-region__spinner {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 18px;
  height: 18px;
  margin-top: -9px;
  margin-left: -9px;
  border-radius: 50%;
  border: 2px solid rgba(99, 102, 241, 0.25);
  border-top-color: var(--c-primary-600, #4f46e5);
  animation: preview-region-spin 0.8s linear infinite;
  pointer-events: none;
  opacity: 0;
  animation-fill-mode: both;
}

.preview-region--refreshing-spinner .preview-region__spinner {
  opacity: 1;
  transition: opacity 0.1s;
}

@keyframes preview-region-spin {
  to {
    transform: rotate(360deg);
  }
}

.preview-region__error-badge {
  position: absolute;
  top: 0.375rem;
  right: 0.375rem;
  max-width: calc(100% - 0.75rem);
  padding: 0.25rem 0.5rem;
  background: #dc2626;
  color: #fff;
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1.2;
  border-radius: 0.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
  pointer-events: auto;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>

<style>
/* In-list drop placeholder. Clamped to a slim bar so a tall/full-width
   widget's ghost threads into a narrow column slot instead of ballooning
   the slot and shoving the layout block ("shuffle"). */
.preview-region--ghost {
  opacity: 0.4;
  max-height: 4.5rem;
  overflow: hidden;
  outline: 2px dashed var(--c-primary-500, #6366f1);
  outline-offset: -2px;
}

/* Drag proxy. forceFallback gives SortableJS a DOM clone appended to
   <body>; detached from its grid/flex/container context, a faithfully
   shrunk render is unreliable per widget type (images collapse, charts
   reflow). Instead the clone is collapsed to a fixed labelled chip that
   follows the cursor — consistent regardless of widget size/type and easy
   to thread into a narrow slot. SortableJS sets inline width/height on the
   clone, so the size override is `!important`; its inline transform
   (per-move translate) is left untouched so it still tracks the pointer. */
.preview-region--drag-fallback {
  width: auto !important;
  height: auto !important;
  min-width: 0 !important;
  min-height: 0 !important;
  max-height: none !important;
  overflow: visible !important;
  background: transparent !important;
  pointer-events: none;
}
.preview-region--drag-fallback > * {
  display: none !important;
}
.preview-region--drag-fallback::after {
  content: '⠿  ' attr(data-widget-label);
  display: inline-flex;
  align-items: center;
  padding: 0.5rem 0.875rem;
  font: 600 0.8125rem/1.2 system-ui, -apple-system, sans-serif;
  color: #fff;
  background: var(--c-primary-600, #4f46e5);
  border-radius: 0.5rem;
  box-shadow: 0 10px 28px rgba(0, 0, 0, 0.28);
  white-space: nowrap;
}
</style>
