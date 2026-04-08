<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Widget } from '../types'
import { useEditorStore } from '../stores/editor'

const props = defineProps<{
  widget: Widget
}>()

const store = useEditorStore()
const open = ref(false)

const collections = computed(() => props.widget.widget_type_collections ?? [])

function getQueryValue(collHandle: string, key: string): any {
  return props.widget.query_config?.[collHandle]?.[key] ?? ''
}

function getTagArray(collHandle: string, key: string): string[] {
  const val = props.widget.query_config?.[collHandle]?.[key]
  return Array.isArray(val) ? val : []
}

function updateQuery(collHandle: string, key: string, value: any) {
  store.updateLocalQueryConfig(props.widget.id, collHandle, key, value)
}

function toggleTag(collHandle: string, key: string, tagSlug: string) {
  const current = getTagArray(collHandle, key)
  const updated = current.includes(tagSlug)
    ? current.filter((s) => s !== tagSlug)
    : [...current, tagSlug]
  updateQuery(collHandle, key, updated)
}
</script>

<template>
  <div v-if="collections.length > 0" class="query-settings">
    <button
      type="button"
      class="query-settings__toggle"
      @click="open = !open"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="query-settings__chevron"
        :class="{ 'query-settings__chevron--open': open }"
        width="14"
        height="14"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
      </svg>
      Query Settings
    </button>

    <div v-show="open" class="query-settings__body">
      <div
        v-for="collHandle in collections"
        :key="collHandle"
        class="query-settings__collection"
      >
        <h5 class="query-settings__collection-label">{{ collHandle }}</h5>

        <div class="query-settings__row">
          <div class="query-settings__field">
            <label class="query-settings__label">Limit</label>
            <input
              type="number"
              min="1"
              :value="getQueryValue(collHandle, 'limit')"
              placeholder="All"
              class="query-settings__input"
              @input="updateQuery(collHandle, 'limit', ($event.target as HTMLInputElement).value)"
            >
          </div>
          <div class="query-settings__field">
            <label class="query-settings__label">Direction</label>
            <select
              :value="getQueryValue(collHandle, 'direction') || 'asc'"
              class="query-settings__input"
              @change="updateQuery(collHandle, 'direction', ($event.target as HTMLSelectElement).value)"
            >
              <option value="asc">Ascending</option>
              <option value="desc">Descending</option>
            </select>
          </div>
        </div>

        <div class="query-settings__field">
          <label class="query-settings__label">Order By</label>
          <select
            :value="getQueryValue(collHandle, 'order_by') || 'sort_order'"
            class="query-settings__input"
            @change="updateQuery(collHandle, 'order_by', ($event.target as HTMLSelectElement).value)"
          >
            <option value="sort_order">Sort Order</option>
            <option value="created_at">Created At</option>
            <option value="updated_at">Updated At</option>
            <option value="published_at">Published At</option>
          </select>
        </div>

        <div v-if="store.tags.length > 0" class="query-settings__tags-row">
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
                  :checked="getTagArray(collHandle, 'include_tags').includes(tag.slug)"
                  class="query-settings__checkbox"
                  @change="toggleTag(collHandle, 'include_tags', tag.slug)"
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
                  :checked="getTagArray(collHandle, 'exclude_tags').includes(tag.slug)"
                  class="query-settings__checkbox"
                  @change="toggleTag(collHandle, 'exclude_tags', tag.slug)"
                >
                {{ tag.name }}
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.query-settings__toggle {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  border: none;
  background: none;
  padding: 0;
  font-size: 0.875rem;
  font-weight: 500;
  color: #4b5563;
  cursor: pointer;
}

.query-settings__toggle:hover {
  color: #111827;
}

.query-settings__chevron {
  transition: transform 0.15s;
}

.query-settings__chevron--open {
  transform: rotate(90deg);
}

.query-settings__body {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-top: 0.75rem;
}

.query-settings__collection {
  padding: 0.75rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
}

.query-settings__collection-label {
  margin: 0 0 0.75rem;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #4b5563;
}

.query-settings__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.query-settings__field {
  margin-bottom: 0.75rem;
}

.query-settings__field:last-child {
  margin-bottom: 0;
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
</style>
