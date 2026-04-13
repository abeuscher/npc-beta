<script setup lang="ts">
import { computed } from 'vue'
import { useEditorStore } from '../stores/editor'
import type { Widget, WidgetPreset } from '../types'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()

const widgetType = computed(() =>
  store.widgetTypes.find((wt) => wt.handle === props.widget.widget_type_handle) ?? null
)

const authoredPresets = computed<WidgetPreset[]>(() =>
  widgetType.value?.presets ?? []
)

const blankPreset = computed<WidgetPreset>(() => {
  const defaults = props.widget.resolved_defaults ?? {}
  const appearanceKeys = new Set(
    (props.widget.widget_type_config_schema ?? [])
      .filter((f) => (f.group ?? 'content') === 'appearance')
      .map((f) => f.key)
  )
  const appearanceDefaults: Record<string, any> = {}
  for (const key of Object.keys(defaults)) {
    if (appearanceKeys.has(key)) {
      appearanceDefaults[key] = defaults[key]
    }
  }
  return {
    handle: '__blank',
    label: 'Blank',
    description: 'Reset appearance to defaults.',
    config: appearanceDefaults,
    appearance_config: {},
  }
})

const allCards = computed<WidgetPreset[]>(() => [blankPreset.value, ...authoredPresets.value])

function apply(preset: WidgetPreset): void {
  store.applyPreset(props.widget.id, preset)
}
</script>

<template>
  <div class="preset-gallery">
    <button
      v-for="preset in allCards"
      :key="preset.handle"
      type="button"
      class="preset-card"
      @click="apply(preset)"
    >
      <div class="preset-card__thumb" aria-hidden="true"></div>
      <div class="preset-card__body">
        <div class="preset-card__label">{{ preset.label }}</div>
        <div v-if="preset.description" class="preset-card__description">
          {{ preset.description }}
        </div>
      </div>
    </button>
  </div>
</template>

<style scoped>
.preset-gallery {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.preset-card {
  display: flex;
  flex-direction: column;
  width: 100%;
  padding: 0;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  background: #fff;
  cursor: pointer;
  overflow: hidden;
  text-align: left;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.preset-card:hover {
  border-color: #9ca3af;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.preset-card__thumb {
  width: 100%;
  aspect-ratio: 16 / 9;
  background: #f3f4f6;
  border-bottom: 1px solid #e5e7eb;
}

.preset-card__body {
  padding: 0.625rem 0.75rem;
}

.preset-card__label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #111827;
}

.preset-card__description {
  margin-top: 0.25rem;
  font-size: 0.75rem;
  color: #6b7280;
  line-height: 1.4;
}
</style>
