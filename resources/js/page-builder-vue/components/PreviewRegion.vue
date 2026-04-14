<script setup lang="ts">
import type { Widget } from '../types'
import { useEditorStore } from '../stores/editor'
import { computed, watch } from 'vue'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()

const isSelected = computed(() => store.selectedBlockId === props.widget.id)

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
</script>

<template>
  <div
    class="preview-region"
    :class="{
      'preview-region--selected': isSelected,
      'preview-region--refreshing-blur': indicatorStage >= 1,
      'preview-region--refreshing-spinner': indicatorStage >= 2,
    }"
    :aria-busy="indicatorStage >= 1 ? 'true' : undefined"
    :data-widget-id="widget.id"
  >
    <template v-if="needsConfig">
      <div class="widget-preview-notice">
        <strong class="widget-preview-notice__label">{{ widget.widget_type_label }}</strong>
        <span class="widget-preview-notice__message">{{ widget.widget_type_required_config!.message }}</span>
      </div>
    </template>
    <template v-else>
      <div class="preview-region__html" v-html="widget.preview_html"></div>
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

.preview-region__overlay:hover {
  outline: 2px solid #282cfc;
  outline-offset: -1px;
}

.preview-region--selected > .preview-region__overlay {
  outline: 2px solid #6366f1;
  outline-offset: -1px;
}

.preview-region--refreshing-blur > .preview-region__overlay {
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
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
.preview-region--ghost {
  opacity: 0.4;
}
</style>
