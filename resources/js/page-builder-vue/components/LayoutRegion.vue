<script setup lang="ts">
import { computed } from 'vue'
import draggable from 'vuedraggable'
import type { PageLayout, Widget } from '../types'
import { useEditorStore } from '../stores/editor'
import PreviewRegion from './PreviewRegion.vue'

const props = defineProps<{
  layout: PageLayout
}>()

const emit = defineEmits<{
  (e: 'drag-end'): void
}>()

const store = useEditorStore()

const isSelected = computed(
  () =>
    store.selectedItemType === 'layout' &&
    store.selectedItemId === props.layout.id
)

const containerStyle = computed(() => {
  const config = props.layout.layout_config ?? {}
  const display = props.layout.display ?? 'grid'
  const styles: Record<string, string> = { display }

  if (display === 'grid') {
    styles.gridTemplateColumns =
      config.grid_template_columns ??
      Array(props.layout.columns).fill('1fr').join(' ')
    if (config.grid_auto_rows) styles.gridAutoRows = config.grid_auto_rows
    if (config.justify_items) styles.justifyItems = config.justify_items
  } else {
    if (config.justify_content) styles.justifyContent = config.justify_content
    if (config.flex_wrap) styles.flexWrap = config.flex_wrap
  }

  if (config.gap) styles.gap = config.gap
  if (config.align_items) styles.alignItems = config.align_items

  // New container style fields (mirrors PageController + ChromeRenderer renderLayoutBlock)
  const spacingMap: Record<string, string> = {
    padding_top: 'paddingTop',
    padding_right: 'paddingRight',
    padding_bottom: 'paddingBottom',
    padding_left: 'paddingLeft',
    margin_top: 'marginTop',
    margin_right: 'marginRight',
    margin_bottom: 'marginBottom',
    margin_left: 'marginLeft',
  }
  for (const [key, cssProp] of Object.entries(spacingMap)) {
    const v = config[key]
    if (v !== undefined && v !== null && v !== '') {
      styles[cssProp] = `${parseInt(v, 10)}px`
    }
  }
  if (config.background_color) styles.backgroundColor = config.background_color

  return styles
})

const isFullWidth = computed(
  () => !!props.layout.layout_config?.full_width
)

function getSlot(slotIdx: number): Widget[] {
  // After populateFromItems normalization, every column 0..columns-1 has an array.
  return (props.layout.slots as any)[slotIdx]
}

function openSlotPicker(slotIdx: number) {
  window.dispatchEvent(
    new CustomEvent('open-widget-picker', {
      detail: {
        layoutId: props.layout.id,
        columnIndex: slotIdx,
        pageId: store.pageId,
      },
    })
  )
}

function slotStyle(slotIdx: number): Record<string, string> {
  if (props.layout.display !== 'flex') return {}
  const basisArr = (props.layout.layout_config?.flex_basis ?? []) as string[]
  const basis = basisArr[slotIdx]
  return basis ? { flexBasis: basis, flexGrow: '0', flexShrink: '0' } : {}
}

function selectLayout() {
  store.selectItem(props.layout.id, 'layout')
}

function onSlotDragStart() {
  store.dragging = true
}

function onSlotDragEnd() {
  store.dragging = false
  emit('drag-end')
}

// Allow widgets to be dropped into slots, but not other layouts (no nesting)
const slotPutFilter = (_to: any, _from: any, dragEl: HTMLElement) => {
  return !dragEl.classList.contains('layout-region')
}
</script>

<template>
  <div
    class="layout-region"
    :class="{ 'layout-region--selected': isSelected }"
    :data-layout-id="layout.id"
  >
    <!-- Left-side select affordance (always visible) -->
    <button
      type="button"
      class="layout-region__selector"
      :title="`Select layout: ${layout.label || 'Column Layout'}`"
      @click.stop="selectLayout"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <rect x="3" y="3" width="7" height="18" rx="1" />
        <rect x="14" y="3" width="7" height="18" rx="1" />
      </svg>
    </button>

    <!-- Hover-revealed handle bar above the layout -->
    <div class="layout-region__handle" @click.stop="selectLayout">
      <span class="layout-region__handle-label">{{
        layout.label || 'Column Layout'
      }}</span>
      <span class="layout-region__handle-meta">
        {{ layout.display }} · {{ layout.columns }} cols
      </span>
    </div>

    <!-- Layout container with column slots -->
    <div
      class="layout-region__container"
      :class="{
        'layout-region__container--contained': !isFullWidth,
        'layout-region__container--dragging': store.dragging,
      }"
      :style="containerStyle"
    >
      <div
        v-for="i in layout.columns"
        :key="i - 1"
        class="layout-region__slot"
        :style="slotStyle(i - 1)"
      >
        <draggable
          :list="getSlot(i - 1)"
          :group="{ name: 'page-items', pull: true, put: slotPutFilter }"
          item-key="id"
          :animation="200"
          :fallback-on-body="true"
          :swap-threshold="0.65"
          ghost-class="preview-region--ghost"
          class="layout-region__slot-list"
          @start="onSlotDragStart"
          @end="onSlotDragEnd"
        >
          <template #item="{ element }">
            <PreviewRegion :widget="element" />
          </template>
          <template #footer>
            <button
              v-if="getSlot(i - 1).length === 0"
              type="button"
              class="layout-region__add-widget"
              @click.stop="openSlotPicker(i - 1)"
            >
              + Add widget
            </button>
          </template>
        </draggable>
      </div>
    </div>
  </div>
</template>

<style scoped>
.layout-region {
  position: relative;
  margin: 0.5rem 0;
}

.layout-region--selected > .layout-region__container:hover {
  outline: 2px solid #282cfc;
}

.layout-region--selected > .layout-region__container {
  outline: 2px solid #6366f1;
}

.layout-region--selected > .layout-region__selector {
  background: #4f46e5;
  color: #fff;
  opacity: 1;
}

.layout-region__selector {
  position: absolute;
  left: -2.25rem;
  top: 50%;
  transform: translateY(-50%);
  z-index: 5;
  width: 1.75rem;
  height: 2.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #e0e7ff;
  color: #4338ca;
  border: 1px solid #c7d2fe;
  border-radius: 0.375rem;
  cursor: pointer;
  opacity: 0.6;
  transition: opacity 0.15s, background-color 0.15s, color 0.15s;
  padding: 0;
}

.layout-region:hover > .layout-region__selector {
  opacity: 1;
}

.layout-region__selector svg {
  width: 1rem;
  height: 1rem;
}

.layout-region__handle {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 100%;
  height: 3.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1rem;
  background: rgba(79, 70, 229, 0.95);
  color: #fff;
  font-size: 2rem;
  font-weight: 500;
  border-radius: 0.375rem 0.375rem 0 0;
  cursor: pointer;
  z-index: 20;
  opacity: 0;
  pointer-events: auto;
  transition: opacity 0.15s;
  box-shadow: 0 -2px 6px rgba(0, 0, 0, 0.1);
}

.layout-region__handle:hover {
  opacity: 1;
}

.layout-region__handle-label {
  font-weight: 600;
}

.layout-region__handle-meta {
  font-size: 1.8rem;
  opacity: 0.85;
  font-variant-numeric: tabular-nums;
}

.layout-region__container {
  min-height: 4rem;
  border: 1px dashed #e5e7eb;
  border-radius: 0.375rem;
  padding: 0.5rem;
}

/* Mirrors .site-container behaviour: 90% width up to a viewport-derived max-width.
   The max-width comes from a CSS variable set on .widget-preview-scope by
   PreviewCanvas, so it tracks the active viewport preset (1920 → 1320, 1024 → 960, etc.). */
.layout-region__container--contained {
  width: 90%;
  max-width: var(--np-preview-container-max-width, 100%);
  margin-left: auto;
  margin-right: auto;
}

.layout-region__slot {
  min-height: 9rem;
}

.layout-region__slot-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-height: 9rem;
}

.layout-region__add-widget {
  display: block;
  width: 100%;
  min-height: 9rem;
  padding: 2rem;
  text-align: center;
  font-size: 2rem;
  font-weight: 600;
  color: #4f46e5;
  background: #f9fafb;
  border: 2px dashed #c7d2fe;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: background-color 0.15s, border-color 0.15s;
}

/* While any drag is in progress, take the add button out of the hit-test path so
   the entire empty slot is one clean drop target for vue-draggable. The button
   is still painted, just unable to intercept pointer events. */
.layout-region__container--dragging .layout-region__add-widget {
  pointer-events: none;
}

.layout-region__add-widget:hover {
  background: #eef2ff;
  border-color: #818cf8;
}
</style>
