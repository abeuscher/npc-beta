<script setup lang="ts">
import { computed } from 'vue'
import { useEditorStore } from '../stores/editor'
import type { FieldDef } from '../types'
import InspectorHeader from './InspectorHeader.vue'
import InspectorTabs from './InspectorTabs.vue'
import InspectorFieldGroup from './InspectorFieldGroup.vue'
import ApplyChangesButton from './ApplyChangesButton.vue'
import WidgetAppearanceControls from './WidgetAppearanceControls.vue'
import SpacingControl from './SpacingControl.vue'
import QuerySettings from './QuerySettings.vue'
import LayoutInspectorPanel from './LayoutInspectorPanel.vue'
import { ref } from 'vue'

const store = useEditorStore()
const activeTab = ref<'content' | 'appearance'>('content')

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
    (f: FieldDef) => (f.group ?? 'content') === 'appearance'
  )
})
</script>

<template>
  <div class="inspector-panel">
    <LayoutInspectorPanel v-if="layout" />

    <template v-else-if="widget">
      <InspectorHeader :widget="widget" />
      <InspectorTabs v-model:active-tab="activeTab" />

      <div class="inspector-panel__body">
        <div v-show="activeTab === 'content'" class="inspector-panel__tab-content">
          <InspectorFieldGroup :fields="contentFields" :widget="widget" empty-message="No content settings for this widget." />
        </div>

        <div v-show="activeTab === 'appearance'" class="inspector-panel__tab-content">
          <WidgetAppearanceControls :widget="widget" />
          <InspectorFieldGroup :fields="appearanceFields" :widget="widget" />
          <QuerySettings :widget="widget" />
          <SpacingControl :widget="widget" />
        </div>
      </div>

      <ApplyChangesButton />
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

.inspector-panel__body {
  border: 1px solid #e5e7eb;
  border-top: 0;
  border-radius: 0 0 0.5rem 0.5rem;
  background: #fff;
  flex: 1;
  overflow-y: auto;
}

.inspector-panel__tab-content {
  padding: 1rem;
}
</style>
