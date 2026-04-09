<script setup lang="ts">
import type { Widget } from '../types'
import { useEditorStore } from '../stores/editor'
import { computed, watch } from 'vue'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()

const isSelected = computed(() => store.selectedBlockId === props.widget.id)

const needsConfig = computed(() => {
  const req = props.widget.widget_type_required_config
  if (!req?.keys?.length) return false
  return req.keys.some(key => {
    const val = props.widget.config[key]
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
    :class="{ 'preview-region--selected': isSelected }"
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
    <div class="preview-region__overlay" @click.stop="handleClick"></div>
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
  transition: box-shadow 0.15s;
}

.preview-region__overlay:hover {
  outline: 2px solid #282cfc;
  outline-offset: -8px;
}

.preview-region--selected > .preview-region__overlay {
  outline: 2px solid #6366f1;
  outline-offset: -8px;
}

</style>

<style>
.preview-region--ghost {
  opacity: 0.4;
}
</style>
