<script setup lang="ts">
import { computed } from 'vue'
import type { Widget } from '../types'
import { useEditorStore } from '../stores/editor'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()

const settings = computed(() => props.widget.query_settings ?? null)
const hasPanel = computed(() => settings.value?.has_panel === true)
const orderByOptions = computed(() => settings.value?.order_by_options ?? {})
const supportsTags = computed(() => settings.value?.supports_tags === true)

function getValue(key: string): any {
  return props.widget.query_config?.[key] ?? ''
}

function getTagArray(key: string): string[] {
  const val = props.widget.query_config?.[key]
  return Array.isArray(val) ? val : []
}

function updateQuery(key: string, value: any) {
  store.updateLocalQueryConfig(props.widget.id, key, value)
}

function toggleTag(key: string, tagSlug: string) {
  const current = getTagArray(key)
  const updated = current.includes(tagSlug)
    ? current.filter((s) => s !== tagSlug)
    : [...current, tagSlug]
  updateQuery(key, updated)
}
</script>

<template>
  <div v-if="hasPanel" class="query-settings">
    <h5 class="query-settings__title">Query Settings</h5>

    <div class="query-settings__body">
      <div class="query-settings__row">
        <div class="query-settings__field">
          <label class="query-settings__label">Limit</label>
          <input
            type="number"
            min="1"
            :value="getValue('limit')"
            placeholder="All"
            class="query-settings__input"
            @input="updateQuery('limit', ($event.target as HTMLInputElement).value)"
          >
        </div>
        <div class="query-settings__field">
          <label class="query-settings__label">Direction</label>
          <select
            :value="getValue('direction') || 'asc'"
            class="query-settings__input"
            @change="updateQuery('direction', ($event.target as HTMLSelectElement).value)"
          >
            <option value="asc">Ascending</option>
            <option value="desc">Descending</option>
          </select>
        </div>
      </div>

      <div class="query-settings__field">
        <label class="query-settings__label">Order By</label>
        <select
          :value="getValue('order_by')"
          class="query-settings__input"
          @change="updateQuery('order_by', ($event.target as HTMLSelectElement).value)"
        >
          <option
            v-for="(label, value) in orderByOptions"
            :key="value"
            :value="value"
          >
            {{ label }}
          </option>
        </select>
      </div>

      <div v-if="supportsTags && store.tags.length > 0" class="query-settings__tags-row">
        <div class="query-settings__field">
          <label class="query-settings__label">Include Tags</label>
          <div class="query-settings__tag-list">
            <label
              v-for="tag in store.tags"
              :key="tag.id"
              class="query-settings__tag-item"
            >
              <input
                type="checkbox"
                :checked="getTagArray('include_tags').includes(tag.slug)"
                class="query-settings__checkbox"
                @change="toggleTag('include_tags', tag.slug)"
              >
              {{ tag.name }}
            </label>
          </div>
        </div>
        <div class="query-settings__field">
          <label class="query-settings__label">Exclude Tags</label>
          <div class="query-settings__tag-list">
            <label
              v-for="tag in store.tags"
              :key="tag.id"
              class="query-settings__tag-item"
            >
              <input
                type="checkbox"
                :checked="getTagArray('exclude_tags').includes(tag.slug)"
                class="query-settings__checkbox"
                @change="toggleTag('exclude_tags', tag.slug)"
              >
              {{ tag.name }}
            </label>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.query-settings {
  margin-top: 1rem;
}

.query-settings__title {
  margin: 0 0 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #4b5563;
}

.query-settings__body {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.75rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
}

.query-settings__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.query-settings__field {
  display: flex;
  flex-direction: column;
}

.query-settings__label {
  display: block;
  margin-bottom: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  color: #4b5563;
}

.query-settings__input {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.375rem 0.5rem;
  font-size: 0.875rem;
  color: #1f2937;
  background: #fff;
}

.query-settings__input:focus {
  outline: none;
  border-color: var(--c-primary-400, #818cf8);
  box-shadow: 0 0 0 1px var(--c-primary-400, #818cf8);
}

.query-settings__tags-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.query-settings__tag-list {
  max-height: 8rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.query-settings__tag-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #374151;
  cursor: pointer;
}

.query-settings__checkbox {
  border-radius: 0.25rem;
  border: 1px solid #d1d5db;
}

html.dark .query-settings__body {
  background: rgb(31 41 55);
  border-color: rgb(75 85 99);
  color: rgb(229 231 235);
}

html.dark .query-settings__input {
  background: rgb(17 24 39);
  color: rgb(229 231 235);
  border-color: rgb(75 85 99);
}
</style>
