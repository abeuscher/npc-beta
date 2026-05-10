<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useEditorStore } from '../stores/editor'
import InspectorTabs from './InspectorTabs.vue'
import BackgroundPanel from './appearance/BackgroundPanel.vue'
import SectionLayoutPanel from './appearance/SectionLayoutPanel.vue'
import LayoutColumnSettingsTab from './LayoutColumnSettingsTab.vue'

type LayoutTab = 'column-settings' | 'margin-padding' | 'background'

const store = useEditorStore()

const layout = computed(() => store.selectedLayout)

const activeTab = computed({
  get: () => store.layoutInspectorTab as LayoutTab,
  set: (v: LayoutTab) => { store.layoutInspectorTab = v },
})

const tabs = [
  { id: 'column-settings' as const, label: 'Column Settings' },
  { id: 'margin-padding' as const, label: 'Margin & Padding' },
  { id: 'background' as const, label: 'Background' },
]

// ── Header — label + delete ────────────────────────────────────────────

const label = ref('')

watch(
  layout,
  (l) => {
    label.value = l?.label ?? ''
  },
  { immediate: true }
)

let labelTimer: ReturnType<typeof setTimeout> | null = null

function onLabelInput() {
  if (!layout.value) return
  if (labelTimer) clearTimeout(labelTimer)
  const id = layout.value.id
  const next = label.value
  labelTimer = setTimeout(() => {
    store.updateLocalLayout(id, { label: next })
  }, 400)
}

function confirmDelete() {
  if (!layout.value) return
  const ok = window.confirm(
    'Deleting this column layout will also delete all widgets inside it. Drag widgets out first to keep them.\n\nDelete this layout?'
  )
  if (ok) {
    store.deleteLayout(layout.value.id)
  }
}

// ── Appearance tabs wiring ─────────────────────────────────────────────

function onBackgroundUpdate(path: string, value: any) {
  if (!layout.value) return
  store.updateLocalLayoutAppearance(layout.value.id, `background.${path}`, value)
}

function onLayoutUpdate(path: string, value: any) {
  if (!layout.value) return
  store.updateLocalLayoutAppearance(layout.value.id, `layout.${path}`, value)
}
</script>

<template>
  <div v-if="layout" class="layout-inspector">
    <!-- Header chrome -->
    <div class="layout-inspector__header">
      <p class="layout-inspector__type-badge">Column Layout</p>
      <div class="layout-inspector__header-row">
        <input
          v-model="label"
          type="text"
          class="layout-inspector__label-input"
          placeholder="Layout label"
          @input="onLabelInput"
        />
        <button
          type="button"
          title="Delete layout"
          class="layout-inspector__delete-btn"
          @click="confirmDelete"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="layout-inspector__icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
        </button>
      </div>
    </div>

    <InspectorTabs
      :tabs="tabs"
      :active-tab="activeTab"
      @update:active-tab="activeTab = $event"
    />

    <div class="layout-inspector__body">
      <!-- Column Settings tab -->
      <LayoutColumnSettingsTab
        v-if="activeTab === 'column-settings'"
        :layout="layout"
      />

      <!-- Margin & Padding tab -->
      <template v-if="activeTab === 'margin-padding'">
        <SectionLayoutPanel
          :config="layout.appearance_config ?? {}"
          :show-full-width="false"
          @update="onLayoutUpdate"
        />
      </template>

      <!-- Background tab — no image sub-tab for layouts -->
      <template v-if="activeTab === 'background'">
        <BackgroundPanel
          :config="layout.appearance_config ?? {}"
          :id-prefix="layout.id"
          :show-image="false"
          @update="onBackgroundUpdate"
        />
      </template>
    </div>
  </div>
</template>

<style scoped>
.layout-inspector {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.layout-inspector__header {
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem 0.5rem 0 0;
  background: #fff;
  padding: 0.75rem 1rem;
}

.layout-inspector__type-badge {
  margin: 0 0 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
}

.layout-inspector__header-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.layout-inspector__label-input {
  flex: 1;
  min-width: 0;
  font-size: 0.875rem;
  font-weight: 500;
  color: #1f2937;
  border: none;
  background: transparent;
  padding: 0;
  outline: none;
}

.layout-inspector__delete-btn {
  flex-shrink: 0;
  padding: 0.25rem;
  border-radius: 0.25rem;
  border: none;
  background: none;
  color: #dc2626;
  cursor: pointer;
}

.layout-inspector__delete-btn:hover {
  background: #fef2f2;
  color: #b91c1c;
}

.layout-inspector__icon {
  width: 0.875rem;
  height: 0.875rem;
}

.layout-inspector__body {
  border: 1px solid #e5e7eb;
  border-top: 0;
  border-radius: 0 0 0.5rem 0.5rem;
  background: #fff;
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
}

html.dark .layout-inspector { color: rgb(229 231 235); }
html.dark .layout-inspector input,
html.dark .layout-inspector select { background: rgb(17 24 39); color: rgb(229 231 235); border-color: rgb(75 85 99); }
</style>
