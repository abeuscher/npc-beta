<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useEditorStore } from '../stores/editor'
import type { FieldDef } from '../types'
import InspectorHeader from './InspectorHeader.vue'
import InspectorTabs from './InspectorTabs.vue'
import InspectorFieldGroup from './InspectorFieldGroup.vue'
import InspectorPresetGallery from './InspectorPresetGallery.vue'
import BackgroundPanel from './appearance/BackgroundPanel.vue'
import TextPanel from './appearance/TextPanel.vue'
import SectionLayoutPanel from './appearance/SectionLayoutPanel.vue'
import QuerySettings from './QuerySettings.vue'
import LayoutInspectorPanel from './LayoutInspectorPanel.vue'

type TopTab = 'content' | 'presets' | 'widget-settings'
type BottomTab = 'background' | 'text' | 'spacing'

const store = useEditorStore()

const topTab = computed({
  get: () => store.inspectorTopTab as TopTab,
  set: (v: TopTab) => { store.inspectorTopTab = v },
})
const bottomTab = computed({
  get: () => store.inspectorBottomTab as BottomTab,
  set: (v: BottomTab) => { store.inspectorBottomTab = v },
})
// Each pane has an independent collapse state. Collapsed = body hidden,
// tab bar still visible. Both can be collapsed simultaneously (edge case —
// inspector becomes just two tab strips).
const topCollapsed = ref(false)
const bottomCollapsed = ref(false)

const isDashboard = computed(() => store.mode === 'dashboard')
const allowedAppearance = computed(() => store.allowedAppearanceFields ?? [])

const topTabs = computed(() => {
  const tabs: { id: TopTab; label: string }[] = [{ id: 'content', label: 'Content' }]
  if (!isDashboard.value) {
    tabs.push({ id: 'presets', label: 'Presets' })
  }
  tabs.push({ id: 'widget-settings', label: 'Widget Settings' })
  return tabs
})

const bottomTabs = computed(() => {
  const tabs: { id: BottomTab; label: string }[] = []
  const allow = (field: string) => !isDashboard.value || allowedAppearance.value.includes(field)

  if (allow('background')) tabs.push({ id: 'background', label: 'Background' })
  if (allow('text')) tabs.push({ id: 'text', label: 'Text' })
  if (allow('layout')) tabs.push({ id: 'spacing', label: 'Margin & Padding' })
  return tabs
})

const widget = computed(() => store.selectedWidget)
const layout = computed(() => store.selectedLayout)

const contentFields = computed(() => {
  if (!widget.value) return [] as FieldDef[]
  return widget.value.widget_type_config_schema.filter(
    (f: FieldDef) => (f.group ?? 'content') === 'content'
  )
})

const appearanceFields = computed(() => {
  if (!widget.value) return [] as FieldDef[]
  return widget.value.widget_type_config_schema.filter(
    (f: FieldDef) => (f.group ?? 'content') !== 'content'
  )
})

function toggleTopCollapse() {
  topCollapsed.value = !topCollapsed.value
}

function toggleBottomCollapse() {
  bottomCollapsed.value = !bottomCollapsed.value
}

// Clicking a tab in a collapsed pane auto-uncollapses it so the user sees
// the content they just asked for.
function setTopTab(id: TopTab) {
  topTab.value = id
  if (topCollapsed.value) topCollapsed.value = false
}

function setBottomTab(id: BottomTab) {
  bottomTab.value = id
  if (bottomCollapsed.value) bottomCollapsed.value = false
}

function onWidgetBackgroundUpdate(path: string, value: any) {
  if (!widget.value) return
  store.updateLocalAppearanceConfig(widget.value.id, `background.${path}`, value)
}

function onWidgetLayoutUpdate(path: string, value: any) {
  if (!widget.value) return
  store.updateLocalAppearanceConfig(widget.value.id, `layout.${path}`, value)
}

function onWidgetUploadImage(file: File) {
  if (!widget.value) return
  store.uploadAppearanceImage(widget.value.id, file)
}

function onWidgetRemoveImage() {
  if (!widget.value) return
  store.removeAppearanceImage(widget.value.id)
}

// If dashboard mode lands on a tab that was hidden, coerce to a valid one.
watch(
  [isDashboard, topTabs, bottomTabs],
  () => {
    if (!topTabs.value.some((t) => t.id === topTab.value)) {
      topTab.value = topTabs.value[0]?.id ?? 'content'
    }
    if (bottomTabs.value.length > 0 && !bottomTabs.value.some((t) => t.id === bottomTab.value)) {
      bottomTab.value = bottomTabs.value[0].id
    }
  },
  { immediate: true },
)
</script>

<template>
  <div class="inspector-panel">
    <LayoutInspectorPanel v-if="layout" />

    <template v-else-if="widget">
      <InspectorHeader :widget="widget" />

      <div class="inspector-panel__panes">
      <!-- Top pane: widget-specific -->
      <section
        class="inspector-pane inspector-pane--top"
        :class="{ 'inspector-pane--collapsed': topCollapsed }"
      >
        <InspectorTabs
          :tabs="topTabs"
          :active-tab="topTab"
          @update:active-tab="setTopTab"
        >
          <template #toolbar>
            <button
              type="button"
              class="inspector-pane__expand"
              :class="{ 'inspector-pane__expand--active': topCollapsed }"
              :title="topCollapsed ? 'Expand this pane' : 'Collapse this pane'"
              @click="toggleTopCollapse"
              aria-label="Toggle top pane"
            >
              <svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true">
                <path
                  d="M4 10 L8 6 L12 10"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.75"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </button>
          </template>
        </InspectorTabs>

        <div class="inspector-pane__body">
          <div v-show="topTab === 'content'" class="inspector-pane__scroll">
            <InspectorFieldGroup
              :fields="contentFields"
              :widget="widget"
              empty-message="No content settings for this widget."
            />
            <QuerySettings :widget="widget" />
          </div>

          <div v-if="!isDashboard" v-show="topTab === 'presets'" class="inspector-pane__scroll">
            <InspectorPresetGallery :widget="widget" />
          </div>

          <div v-show="topTab === 'widget-settings'" class="inspector-pane__scroll">
            <InspectorFieldGroup
              :fields="appearanceFields"
              :widget="widget"
              empty-message="No widget-specific settings."
            />
          </div>
        </div>
      </section>

      <!-- Bottom pane: universal appearance -->
      <section
        class="inspector-pane inspector-pane--bottom"
        :class="{ 'inspector-pane--collapsed': bottomCollapsed }"
      >
        <InspectorTabs
          :tabs="bottomTabs"
          :active-tab="bottomTab"
          @update:active-tab="setBottomTab"
        >
          <template #toolbar>
            <button
              type="button"
              class="inspector-pane__expand"
              :class="{ 'inspector-pane__expand--active': bottomCollapsed }"
              :title="bottomCollapsed ? 'Expand this pane' : 'Collapse this pane'"
              @click="toggleBottomCollapse"
              aria-label="Toggle bottom pane"
            >
              <svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true">
                <path
                  d="M4 6 L8 10 L12 6"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.75"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </button>
          </template>
        </InspectorTabs>

        <div class="inspector-pane__body">
          <div v-show="bottomTab === 'background'" class="inspector-pane__scroll">
            <BackgroundPanel
              :config="widget.appearance_config"
              :image-url="widget.appearance_image_url"
              :id-prefix="widget.id"
              @update="onWidgetBackgroundUpdate"
              @upload-image="onWidgetUploadImage"
              @remove-image="onWidgetRemoveImage"
            />
          </div>
          <div v-show="bottomTab === 'text'" class="inspector-pane__scroll">
            <TextPanel :widget="widget" />
          </div>
          <div v-if="!isDashboard" v-show="bottomTab === 'spacing'" class="inspector-pane__scroll">
            <SectionLayoutPanel
              :config="widget.appearance_config"
              :full-width-disabled="widget.layout_id !== null"
              full-width-disabled-reason="The parent column controls width for column widgets"
              @update="onWidgetLayoutUpdate"
            />
          </div>
        </div>
      </section>
      </div>
    </template>

    <div v-else class="inspector-panel__placeholder">
      Select a block to edit its settings.
    </div>
  </div>
</template>

<style scoped>
.inspector-panel {
  display: flex;
  flex-direction: column;
  height: 100%;
  margin-top: 2rem;
  min-height: 0;
}

.inspector-panel__placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 200px;
  border: 2px dashed #e5e7eb;
  border-radius: 0.5rem;
  padding: 1.5rem;
  text-align: center;
  font-size: 0.875rem;
  color: #9ca3af;
}

/* ── Pane stack ──────────────────────────────────────────────────────────── */

/* Wrapper for the two panes — fills the space after the header, so each
 * pane's 50% max-height is measured against a known container. */
.inspector-panel__panes {
  flex: 1 1 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  background: var(--np-control-bar-bg);
}

.inspector-pane {
  display: flex;
  flex-direction: column;
  flex: 1 1 0;
  min-height: 0;
  max-height: 50%;
  transition: flex-grow 0.18s ease;
}

/* Collapsed pane shrinks to just its tab bar. Sibling stays capped at 50%,
 * so the freed space is left empty rather than redistributed. */
.inspector-pane--collapsed {
  flex: 0 0 auto;
  max-height: none;
}

.inspector-pane--collapsed .inspector-pane__body {
  display: none;
}

/* ── Body + scroll ───────────────────────────────────────────────────────── */

.inspector-pane__body {
  flex: 1 1 0;
  min-height: 0;
  border: 1px solid var(--np-control-border);
  border-top: 0;
  border-radius: 0 0 0.5rem 0.5rem;
  background: var(--np-control-chip-bg);
  display: flex;
  flex-direction: column;
}

.inspector-pane__scroll {
  flex: 1 1 0;
  min-height: 0;
  overflow-y: auto;
  padding: 1rem;
}

/* ── Expand / collapse button in tab toolbar ─────────────────────────────── */

.inspector-pane__expand {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.5rem;
  padding: 0;
  border: 1px solid transparent;
  border-radius: var(--np-control-radius-sm);
  background: transparent;
  color: var(--np-control-icon-default);
  cursor: pointer;
  transition: var(--np-control-transition);
}

.inspector-pane__expand:hover {
  background: var(--np-control-hover-tint);
  color: var(--np-control-icon-active);
}

.inspector-pane__expand--active {
  background: var(--np-control-active-bg);
  color: var(--np-control-icon-active);
  box-shadow: var(--np-control-active-shadow);
}

html.dark .inspector-panel__placeholder { border-color: rgb(75 85 99); color: rgb(107 114 128); }
</style>
