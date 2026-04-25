<script setup lang="ts">
import { computed } from 'vue'
import type { FieldDef, Widget } from '../types'
import InspectorField from './InspectorField.vue'

const props = defineProps<{
  fields: FieldDef[]
  widget: Widget
  emptyMessage?: string
}>()

const primaryFields = computed(() =>
  props.fields.filter((f) => !f.advanced)
)

const advancedFields = computed(() =>
  props.fields.filter((f) => !!f.advanced)
)

/**
 * Group consecutive fields with the same non-semantic `group` value
 * into layout groups rendered as 2-column grids.
 */
function groupedFields(fields: FieldDef[]): Array<{ layout: boolean; group?: string; items: FieldDef[] }> {
  const semanticGroups = new Set(['content', 'appearance'])
  const result: Array<{ layout: boolean; group?: string; items: FieldDef[] }> = []

  for (const field of fields) {
    const g = field.group
    const isLayoutGroup = !!g && !semanticGroups.has(g)

    if (isLayoutGroup) {
      const last = result[result.length - 1]
      if (last && last.layout && last.group === g) {
        last.items.push(field)
      } else {
        result.push({ layout: true, group: g, items: [field] })
      }
    } else {
      result.push({ layout: false, items: [field] })
    }
  }

  return result
}

const primaryGrouped = computed(() => groupedFields(primaryFields.value))
</script>

<template>
  <div class="inspector-field-group">
    <template v-if="primaryFields.length === 0 && advancedFields.length === 0 && emptyMessage">
      <p class="inspector-field-group__empty">{{ emptyMessage }}</p>
    </template>

    <template v-for="(chunk, i) in primaryGrouped" :key="i">
      <div
        v-if="chunk.layout"
        class="inspector-field-group__grid"
        :class="{ 'inspector-field-group__grid--dense': chunk.items.length > 2 }"
      >
        <InspectorField
          v-for="field in chunk.items"
          :key="field.key"
          :field="field"
          :widget="widget"
        />
      </div>
      <template v-else>
        <InspectorField
          v-for="field in chunk.items"
          :key="field.key"
          :field="field"
          :widget="widget"
        />
      </template>
    </template>

    <div v-if="advancedFields.length > 0" class="inspector-field-group__advanced">
      <hr class="inspector-field-group__divider">
      <h5 class="inspector-field-group__advanced-heading">Advanced</h5>
      <InspectorField
        v-for="field in advancedFields"
        :key="field.key"
        :field="field"
        :widget="widget"
      />
    </div>
  </div>
</template>

<style scoped>
.inspector-field-group {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.inspector-field-group__empty {
  font-size: 0.875rem;
  color: #9ca3af;
  margin: 0;
}

.inspector-field-group__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.inspector-field-group__grid--dense {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: start;
}

.inspector-field-group__grid--dense :deep(.color-picker__popover) {
  min-width: 14rem;
}

.inspector-field-group__advanced {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-top: 0.25rem;
}

.inspector-field-group__divider {
  border: 0;
  border-top: 1px solid #e5e7eb;
  margin: 0;
}

.inspector-field-group__advanced-heading {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #4b5563;
}

html.dark .inspector-field-group__divider {
  border-top-color: rgb(75 85 99);
}

html.dark .inspector-field-group__advanced-heading {
  color: rgb(229 231 235);
}
</style>
