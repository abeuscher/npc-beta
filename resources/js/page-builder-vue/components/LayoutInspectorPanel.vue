<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useEditorStore } from '../stores/editor'

const store = useEditorStore()

const layout = computed(() => store.selectedLayout)

// Local label state with debounced save through the store helper
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

// ── Display toggle ──────────────────────────────────────────────────────

function setDisplay(display: 'flex' | 'grid') {
  if (!layout.value) return
  store.updateLocalLayout(layout.value.id, { display })
}

// ── Columns count ───────────────────────────────────────────────────────

function onColumnsChange(e: Event) {
  if (!layout.value) return
  const value = parseInt((e.target as HTMLInputElement).value, 10)
  if (isNaN(value) || value < 1 || value > 12) return

  // When columns increase in grid mode, extend grid_template_columns
  const config = { ...(layout.value.layout_config ?? {}) }
  if (layout.value.display === 'grid') {
    const cur = (config.grid_template_columns ?? '').toString().trim().split(/\s+/).filter(Boolean)
    while (cur.length < value) cur.push('1fr')
    cur.length = value
    config.grid_template_columns = cur.join(' ')
  }
  // When in flex mode, resize the per-column flex_basis array
  if (layout.value.display === 'flex') {
    const basis = Array.isArray(config.flex_basis) ? [...config.flex_basis] : []
    while (basis.length < value) basis.push('auto')
    basis.length = value
    config.flex_basis = basis
  }

  store.updateLocalLayout(layout.value.id, { columns: value, layout_config: config })
}

// ── Grid controls ───────────────────────────────────────────────────────

const gridTemplatePresets = [
  { value: '1fr 1fr', label: 'Equal halves (1fr 1fr)' },
  { value: '2fr 1fr', label: 'Wide left (2fr 1fr)' },
  { value: '1fr 2fr', label: 'Wide right (1fr 2fr)' },
  { value: '3fr 1fr', label: '3:1 (3fr 1fr)' },
  { value: '1fr 3fr', label: '1:3 (1fr 3fr)' },
  { value: '1fr 1fr 1fr', label: 'Three equal (1fr 1fr 1fr)' },
  { value: '1fr 2fr 1fr', label: 'Center wide (1fr 2fr 1fr)' },
  { value: '2fr 1fr 1fr', label: 'Wide left + 2 (2fr 1fr 1fr)' },
  { value: '1fr 1fr 1fr 1fr', label: 'Four equal (1fr 1fr 1fr 1fr)' },
]

const gridTemplateValue = computed(
  () => layout.value?.layout_config?.grid_template_columns ?? ''
)

const gridTemplateMode = ref<'preset' | 'manual'>('preset')

watch(
  layout,
  (l) => {
    if (!l) return
    const cur = l.layout_config?.grid_template_columns ?? ''
    gridTemplateMode.value = gridTemplatePresets.some((p) => p.value === cur)
      ? 'preset'
      : cur
      ? 'manual'
      : 'preset'
  },
  { immediate: true }
)

function setGridTemplate(value: string) {
  if (!layout.value) return
  store.updateLocalLayout(layout.value.id, {
    layout_config: { grid_template_columns: value },
  })
}

const gapPresets = ['0', '0.5rem', '1rem', '1.5rem', '2rem', '3rem']

function setLayoutConfigKey(key: string, value: string) {
  if (!layout.value) return
  store.updateLocalLayout(layout.value.id, {
    layout_config: { [key]: value },
  })
}

// ── Flex controls ──────────────────────────────────────────────────────

function setFlexBasis(slotIdx: number, value: string) {
  if (!layout.value) return
  const cur = Array.isArray(layout.value.layout_config?.flex_basis)
    ? [...(layout.value.layout_config!.flex_basis as string[])]
    : []
  while (cur.length < (layout.value.columns ?? 0)) cur.push('auto')
  cur[slotIdx] = value
  store.updateLocalLayout(layout.value.id, {
    layout_config: { flex_basis: cur },
  })
}

function getFlexBasis(slotIdx: number): string {
  const arr = (layout.value?.layout_config?.flex_basis ?? []) as string[]
  return arr[slotIdx] ?? 'auto'
}

// ── Delete ─────────────────────────────────────────────────────────────

function confirmDelete() {
  if (!layout.value) return
  const ok = window.confirm(
    'Deleting this column layout will also delete all widgets inside it. Drag widgets out first to keep them.\n\nDelete this layout?'
  )
  if (ok) {
    store.deleteLayout(layout.value.id)
  }
}
</script>

<template>
  <div v-if="layout" class="layout-inspector">
    <div class="layout-inspector__header">
      <div class="layout-inspector__type">Column Layout</div>
      <input
        v-model="label"
        type="text"
        class="layout-inspector__label"
        placeholder="Layout label"
        @input="onLabelInput"
      />
    </div>

    <div class="layout-inspector__body">
      <!-- Display toggle -->
      <div class="layout-inspector__field">
        <label class="layout-inspector__label-row">Display</label>
        <div class="layout-inspector__toggle-group">
          <button
            type="button"
            class="layout-inspector__toggle"
            :class="{
              'layout-inspector__toggle--active': layout.display === 'grid',
            }"
            @click="setDisplay('grid')"
          >
            display: grid
          </button>
          <button
            type="button"
            class="layout-inspector__toggle"
            :class="{
              'layout-inspector__toggle--active': layout.display === 'flex',
            }"
            @click="setDisplay('flex')"
          >
            display: flex
          </button>
        </div>
        <p class="layout-inspector__hint">
          Controls how child widgets are arranged within this layout.
        </p>
      </div>

      <!-- Columns count -->
      <div class="layout-inspector__field">
        <label class="layout-inspector__label-row" for="layout-columns">
          Columns
        </label>
        <input
          id="layout-columns"
          type="number"
          min="1"
          max="12"
          :value="layout.columns"
          class="layout-inspector__input"
          @change="onColumnsChange"
        />
        <p class="layout-inspector__hint">
          Number of column slots in this layout (1–12).
        </p>
      </div>

      <!-- ── Grid controls ─────────────────────────────────────────── -->
      <template v-if="layout.display === 'grid'">
        <div class="layout-inspector__section-divider">Grid properties</div>

        <div class="layout-inspector__field">
          <label class="layout-inspector__label-row">grid-template-columns</label>
          <div class="layout-inspector__mode-toggle">
            <button
              type="button"
              class="layout-inspector__mode-btn"
              :class="{
                'layout-inspector__mode-btn--active':
                  gridTemplateMode === 'preset',
              }"
              @click="gridTemplateMode = 'preset'"
            >
              Preset
            </button>
            <button
              type="button"
              class="layout-inspector__mode-btn"
              :class="{
                'layout-inspector__mode-btn--active':
                  gridTemplateMode === 'manual',
              }"
              @click="gridTemplateMode = 'manual'"
            >
              Manual
            </button>
          </div>

          <select
            v-if="gridTemplateMode === 'preset'"
            class="layout-inspector__input"
            :value="gridTemplateValue"
            @change="setGridTemplate(($event.target as HTMLSelectElement).value)"
          >
            <option value="">— Select a preset —</option>
            <option
              v-for="preset in gridTemplatePresets"
              :key="preset.value"
              :value="preset.value"
            >
              {{ preset.label }}
            </option>
          </select>

          <input
            v-else
            type="text"
            class="layout-inspector__input"
            :value="gridTemplateValue"
            placeholder="e.g. 1fr 2fr 1fr or repeat(3, 1fr)"
            @input="setGridTemplate(($event.target as HTMLInputElement).value)"
          />
          <p class="layout-inspector__hint">Defines the width of each column.</p>
        </div>

        <div class="layout-inspector__field">
          <label class="layout-inspector__label-row" for="layout-gap-grid">gap</label>
          <div class="layout-inspector__input-with-presets">
            <input
              id="layout-gap-grid"
              type="text"
              class="layout-inspector__input"
              :value="layout.layout_config?.gap ?? ''"
              placeholder="e.g. 1rem"
              @input="setLayoutConfigKey('gap', ($event.target as HTMLInputElement).value)"
            />
            <select
              class="layout-inspector__preset-select"
              @change="setLayoutConfigKey('gap', ($event.target as HTMLSelectElement).value)"
            >
              <option value="">Preset…</option>
              <option v-for="g in gapPresets" :key="g" :value="g">{{ g }}</option>
            </select>
          </div>
          <p class="layout-inspector__hint">Space between columns and rows.</p>
        </div>

        <div class="layout-inspector__field">
          <label class="layout-inspector__label-row" for="layout-grid-auto-rows">grid-auto-rows</label>
          <input
            id="layout-grid-auto-rows"
            type="text"
            class="layout-inspector__input"
            :value="layout.layout_config?.grid_auto_rows ?? ''"
            placeholder="e.g. auto, minmax(100px, auto), 1fr"
            @input="setLayoutConfigKey('grid_auto_rows', ($event.target as HTMLInputElement).value)"
          />
          <p class="layout-inspector__hint">Default height for rows not explicitly sized.</p>
        </div>

        <div class="layout-inspector__field">
          <label class="layout-inspector__label-row" for="layout-grid-align-items">align-items</label>
          <select
            id="layout-grid-align-items"
            class="layout-inspector__input"
            :value="layout.layout_config?.align_items ?? 'stretch'"
            @change="setLayoutConfigKey('align_items', ($event.target as HTMLSelectElement).value)"
          >
            <option value="stretch">stretch</option>
            <option value="start">start</option>
            <option value="center">center</option>
            <option value="end">end</option>
            <option value="baseline">baseline</option>
          </select>
          <p class="layout-inspector__hint">Vertical alignment of items within their grid cell.</p>
        </div>

        <div class="layout-inspector__field">
          <label class="layout-inspector__label-row" for="layout-grid-justify-items">justify-items</label>
          <select
            id="layout-grid-justify-items"
            class="layout-inspector__input"
            :value="layout.layout_config?.justify_items ?? 'stretch'"
            @change="setLayoutConfigKey('justify_items', ($event.target as HTMLSelectElement).value)"
          >
            <option value="stretch">stretch</option>
            <option value="start">start</option>
            <option value="center">center</option>
            <option value="end">end</option>
          </select>
          <p class="layout-inspector__hint">Horizontal alignment of items within their grid cell.</p>
        </div>
      </template>

      <!-- ── Flex controls ─────────────────────────────────────────── -->
      <template v-if="layout.display === 'flex'">
        <div class="layout-inspector__section-divider">Flex properties</div>

        <div class="layout-inspector__field">
          <label class="layout-inspector__label-row" for="layout-flex-justify">justify-content</label>
          <select
            id="layout-flex-justify"
            class="layout-inspector__input"
            :value="layout.layout_config?.justify_content ?? 'flex-start'"
            @change="setLayoutConfigKey('justify_content', ($event.target as HTMLSelectElement).value)"
          >
            <option value="flex-start">flex-start</option>
            <option value="flex-end">flex-end</option>
            <option value="center">center</option>
            <option value="space-between">space-between</option>
            <option value="space-around">space-around</option>
            <option value="space-evenly">space-evenly</option>
          </select>
          <p class="layout-inspector__hint">How items are distributed along the main axis.</p>
        </div>

        <div class="layout-inspector__field">
          <label class="layout-inspector__label-row" for="layout-flex-align">align-items</label>
          <select
            id="layout-flex-align"
            class="layout-inspector__input"
            :value="layout.layout_config?.align_items ?? 'stretch'"
            @change="setLayoutConfigKey('align_items', ($event.target as HTMLSelectElement).value)"
          >
            <option value="stretch">stretch</option>
            <option value="flex-start">flex-start</option>
            <option value="flex-end">flex-end</option>
            <option value="center">center</option>
            <option value="baseline">baseline</option>
          </select>
          <p class="layout-inspector__hint">How items are aligned on the cross axis.</p>
        </div>

        <div class="layout-inspector__field">
          <label class="layout-inspector__label-row" for="layout-gap-flex">gap</label>
          <div class="layout-inspector__input-with-presets">
            <input
              id="layout-gap-flex"
              type="text"
              class="layout-inspector__input"
              :value="layout.layout_config?.gap ?? ''"
              placeholder="e.g. 1rem"
              @input="setLayoutConfigKey('gap', ($event.target as HTMLInputElement).value)"
            />
            <select
              class="layout-inspector__preset-select"
              @change="setLayoutConfigKey('gap', ($event.target as HTMLSelectElement).value)"
            >
              <option value="">Preset…</option>
              <option v-for="g in gapPresets" :key="g" :value="g">{{ g }}</option>
            </select>
          </div>
          <p class="layout-inspector__hint">Space between flex items.</p>
        </div>

        <div class="layout-inspector__field">
          <label class="layout-inspector__label-row">flex-wrap</label>
          <div class="layout-inspector__toggle-group">
            <button
              type="button"
              class="layout-inspector__toggle"
              :class="{
                'layout-inspector__toggle--active':
                  (layout.layout_config?.flex_wrap ?? 'nowrap') === 'nowrap',
              }"
              @click="setLayoutConfigKey('flex_wrap', 'nowrap')"
            >
              nowrap
            </button>
            <button
              type="button"
              class="layout-inspector__toggle"
              :class="{
                'layout-inspector__toggle--active':
                  layout.layout_config?.flex_wrap === 'wrap',
              }"
              @click="setLayoutConfigKey('flex_wrap', 'wrap')"
            >
              wrap
            </button>
          </div>
          <p class="layout-inspector__hint">Whether items wrap to a new line when they overflow.</p>
        </div>

        <div class="layout-inspector__field">
          <label class="layout-inspector__label-row">Per-column flex-basis</label>
          <div
            v-for="i in layout.columns"
            :key="i - 1"
            class="layout-inspector__per-column-row"
          >
            <span class="layout-inspector__per-column-label">Col {{ i }}</span>
            <input
              type="text"
              class="layout-inspector__input"
              :value="getFlexBasis(i - 1)"
              placeholder="auto, 50%, 200px, etc."
              @input="setFlexBasis(i - 1, ($event.target as HTMLInputElement).value)"
            />
          </div>
          <p class="layout-inspector__hint">Base width of each column before flex grow/shrink.</p>
        </div>
      </template>
    </div>

    <div class="layout-inspector__footer">
      <button type="button" class="layout-inspector__delete" @click="confirmDelete">
        Delete Layout
      </button>
    </div>
  </div>
</template>

<style scoped>
.layout-inspector {
  display: flex;
  flex-direction: column;
  height: 100%;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  background: #fff;
}

.layout-inspector__header {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
}

.layout-inspector__type {
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6366f1;
  margin-bottom: 0.25rem;
}

.layout-inspector__label {
  width: 100%;
  font-size: 0.9375rem;
  font-weight: 600;
  color: #111827;
  border: none;
  background: transparent;
  padding: 0;
  outline: none;
}

.layout-inspector__body {
  flex: 1;
  padding: 1rem;
  overflow-y: auto;
}

.layout-inspector__field {
  margin-bottom: 1.25rem;
}

.layout-inspector__label-row {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.375rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.layout-inspector__input {
  width: 100%;
  padding: 0.4rem 0.625rem;
  font-size: 0.8125rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  background: #fff;
  color: #111827;
  outline: none;
}

.layout-inspector__input:focus {
  border-color: #6366f1;
  box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

.layout-inspector__hint {
  margin: 0.375rem 0 0;
  font-size: 0.6875rem;
  color: #9ca3af;
  line-height: 1.4;
}

.layout-inspector__toggle-group {
  display: flex;
  gap: 0.25rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  padding: 0.125rem;
  background: #f9fafb;
}

.layout-inspector__toggle {
  flex: 1;
  padding: 0.375rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 500;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  color: #6b7280;
  background: transparent;
  border: none;
  border-radius: 0.25rem;
  cursor: pointer;
}

.layout-inspector__toggle--active {
  background: #fff;
  color: #4f46e5;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.layout-inspector__mode-toggle {
  display: inline-flex;
  margin-bottom: 0.375rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  overflow: hidden;
}

.layout-inspector__mode-btn {
  padding: 0.25rem 0.5rem;
  font-size: 0.6875rem;
  font-weight: 500;
  color: #6b7280;
  background: #fff;
  border: none;
  cursor: pointer;
}

.layout-inspector__mode-btn--active {
  background: #4f46e5;
  color: #fff;
}

.layout-inspector__input-with-presets {
  display: flex;
  gap: 0.375rem;
}

.layout-inspector__input-with-presets > .layout-inspector__input {
  flex: 1;
}

.layout-inspector__preset-select {
  width: auto;
  padding: 0.4rem 0.5rem;
  font-size: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  background: #f9fafb;
  color: #374151;
  cursor: pointer;
}

.layout-inspector__section-divider {
  margin: 0.5rem 0 1rem;
  padding-bottom: 0.375rem;
  border-bottom: 1px solid #e5e7eb;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6366f1;
}

.layout-inspector__per-column-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.375rem;
}

.layout-inspector__per-column-label {
  flex-shrink: 0;
  width: 3rem;
  font-size: 0.6875rem;
  font-weight: 500;
  color: #6b7280;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.layout-inspector__footer {
  padding: 0.75rem 1rem;
  border-top: 1px solid #e5e7eb;
}

.layout-inspector__delete {
  width: 100%;
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #dc2626;
  background: #fff;
  border: 1px solid #fecaca;
  border-radius: 0.375rem;
  cursor: pointer;
}

.layout-inspector__delete:hover {
  background: #fef2f2;
  border-color: #fca5a5;
}
</style>
