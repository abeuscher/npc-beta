<script setup lang="ts">
import type { Widget } from '../types'
import { useEditorStore } from '../stores/editor'
import { computed } from 'vue'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()

const isSelected = computed(() => store.selectedBlockId === props.widget.id)

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
    <div class="preview-region__html" v-html="widget.preview_html"></div>
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
  box-shadow: inset 0 0 0 2px rgba(99, 102, 241, 0.3);
}

.preview-region--selected > .preview-region__overlay {
  box-shadow: inset 0 0 0 2px #6366f1;
}
</style>
