<script setup lang="ts">
import { computed } from 'vue'
import { ref } from 'vue'
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

const advancedOpen = ref(false)

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
      <div v-if="chunk.layout" class="inspector-field-group__grid">
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

    <div v-if="advancedFields.length > 0" class="inspector-field-group__accordion">
      <button
        type="button"
        class="inspector-field-group__accordion-btn"
        @click="advancedOpen = !advancedOpen"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="inspector-field-group__chevron"
          :class="{ 'inspector-field-group__chevron--open': advancedOpen }"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        Carousel Settings
      </button>

      <div v-show="advancedOpen" class="inspector-field-group__accordion-body">
        <InspectorField
          v-for="field in advancedFields"
          :key="field.key"
          :field="field"
          :widget="widget"
        />
      </div>
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

.inspector-field-group__accordion {
  margin-top: 0.25rem;
}

.inspector-field-group__accordion-btn {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #4b5563;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}

.inspector-field-group__accordion-btn:hover {
  color: #111827;
}

.inspector-field-group__chevron {
  width: 0.875rem;
  height: 0.875rem;
  transition: transform 0.15s;
}

.inspector-field-group__chevron--open {
  transform: rotate(90deg);
}

.inspector-field-group__accordion-body {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-top: 0.75rem;
}
</style>
