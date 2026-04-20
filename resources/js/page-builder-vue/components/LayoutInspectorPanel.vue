<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useEditorStore } from '../stores/editor'
import ColorPicker from './primitives/ColorPicker.vue'

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

function setLayoutConfigKey(key: string, value: any) {
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

// ── Container appearance ───────────────────────────────────────────────

const fullWidth = computed(() => !!layout.value?.layout_config?.full_width)
const backgroundColor = computed(() => layout.value?.layout_config?.background_color ?? '')
const bgField = { key: 'background_color', label: 'Background Color', type: 'color', helper: '#ffffff' }

const paddingKeys = [
  { key: 'padding_left', label: 'Left' },
  { key: 'padding_top', label: 'Top' },
  { key: 'padding_right', label: 'Right' },
  { key: 'padding_bottom', label: 'Bottom' },
]

const marginKeys = [
  { key: 'margin_left', label: 'Left' },
  { key: 'margin_top', label: 'Top' },
  { key: 'margin_right', label: 'Right' },
  { key: 'margin_bottom', label: 'Bottom' },
]

const lc = computed(() => layout.value?.layout_config ?? {})

const paddingAll = computed(() => {
  const t = lc.value.padding_top ?? ''
  const r = lc.value.padding_right ?? ''
  const b = lc.value.padding_bottom ?? ''
  const l = lc.value.padding_left ?? ''
  return (t === r && r === b && b === l && t !== '') ? t : ''
})

const paddingAllPlaceholder = computed(() => {
  const t = lc.value.padding_top ?? ''
  const r = lc.value.padding_right ?? ''
  const b = lc.value.padding_bottom ?? ''
  const l = lc.value.padding_left ?? ''
  return (t === r && r === b && b === l) ? '' : 'mixed'
})

const marginAll = computed(() => {
  const t = lc.value.margin_top ?? ''
  const r = lc.value.margin_right ?? ''
  const b = lc.value.margin_bottom ?? ''
  const l = lc.value.margin_left ?? ''
  return (t === r && r === b && b === l && t !== '') ? t : ''
})

const marginAllPlaceholder = computed(() => {
  const t = lc.value.margin_top ?? ''
  const r = lc.value.margin_right ?? ''
  const b = lc.value.margin_bottom ?? ''
  const l = lc.value.margin_left ?? ''
  return (t === r && r === b && b === l) ? '' : 'mixed'
})

function setPaddingAll(value: string) {
  if (!layout.value) return
  store.updateLocalLayout(layout.value.id, {
    layout_config: {
      padding_top: value,
      padding_right: value,
      padding_bottom: value,
      padding_left: value,
    },
  })
}

function setMarginAll(value: string) {
  if (!layout.value) return
  store.updateLocalLayout(layout.value.id, {
    layout_config: {
      margin_top: value,
      margin_right: value,
      margin_bottom: value,
      margin_left: value,
    },
  })
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
    <!-- Header chrome to match widget inspector -->
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

    <!-- Single tab to mirror the widget inspector tab strip -->
    <div class="layout-inspector__tabs">
      <button type="button" class="layout-inspector__tab-btn layout-inspector__tab-btn--active">
        Appearance
      </button>
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

      <!-- ── Container appearance ──────────────────────────────────── -->
      <div class="layout-inspector__section-divider">Container</div>

      <div class="layout-inspector__field">
        <label class="layout-inspector__checkbox-row">
          <input
            type="checkbox"
            :checked="fullWidth"
            class="layout-inspector__checkbox"
            @change="setLayoutConfigKey('full_width', ($event.target as HTMLInputElement).checked)"
          >
          <span>Full width</span>
        </label>
        <p class="layout-inspector__hint">When off, the layout is constrained to the site content container.</p>
      </div>

      <div class="layout-inspector__field">
        <ColorPicker
          :model-value="backgroundColor"
          label="Background Color"
          :placeholder="bgField.helper"
          @update:model-value="setLayoutConfigKey('background_color', $event)"
        />
      </div>

      <div class="layout-inspector__field">
        <p class="layout-inspector__label-row">Padding (px)</p>
        <div class="layout-inspector__spacing-grid">
          <div class="layout-inspector__spacing-cell">
            <label class="layout-inspector__spacing-cell-label">All</label>
            <input
              type="number"
              min="0"
              :value="paddingAll"
              :placeholder="paddingAllPlaceholder"
              class="layout-inspector__spacing-input"
              @input="setPaddingAll(($event.target as HTMLInputElement).value)"
            >
          </div>
          <div v-for="item in paddingKeys" :key="item.key" class="layout-inspector__spacing-cell">
            <label class="layout-inspector__spacing-cell-label">{{ item.label }}</label>
            <input
              type="number"
              min="0"
              :value="lc[item.key] ?? ''"
              class="layout-inspector__spacing-input"
              @input="setLayoutConfigKey(item.key, ($event.target as HTMLInputElement).value)"
            >
          </div>
        </div>
      </div>

      <div class="layout-inspector__field">
        <p class="layout-inspector__label-row">Margin (px)</p>
        <div class="layout-inspector__spacing-grid">
          <div class="layout-inspector__spacing-cell">
            <label class="layout-inspector__spacing-cell-label">All</label>
            <input
              type="number"
              min="0"
              :value="marginAll"
              :placeholder="marginAllPlaceholder"
              class="layout-inspector__spacing-input"
              @input="setMarginAll(($event.target as HTMLInputElement).value)"
            >
          </div>
          <div v-for="item in marginKeys" :key="item.key" class="layout-inspector__spacing-cell">
            <label class="layout-inspector__spacing-cell-label">{{ item.label }}</label>
            <input
              type="number"
              min="0"
              :value="lc[item.key] ?? ''"
              class="layout-inspector__spacing-input"
              @input="setLayoutConfigKey(item.key, ($event.target as HTMLInputElement).value)"
            >
          </div>
        </div>
      </div>
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

.layout-inspector__tabs {
  display: flex;
  border-left: 1px solid #e5e7eb;
  border-right: 1px solid #e5e7eb;
  background: #f9fafb;
  padding: 0.25rem 0 0 0.25rem;
}

.layout-inspector__tab-btn {
  position: relative;
  border: 1px solid #e5e7eb;
  border-radius: 0.375rem 0.375rem 0 0;
  margin-bottom: -1px;
  margin-left: 0.25rem;
  padding: 0.5rem 1rem;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #374151;
  background: transparent;
  opacity: 0.6;
  cursor: default;
}

.layout-inspector__tab-btn--active {
  background: #fff;
  opacity: 1;
  border-bottom-color: #fff;
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

.layout-inspector__checkbox-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.875rem;
  color: #374151;
}

.layout-inspector__checkbox {
  border-radius: 0.25rem;
  border: 1px solid #d1d5db;
  color: var(--c-primary-600, #4f46e5);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.layout-inspector__spacing-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.5rem;
}

.layout-inspector__spacing-cell-label {
  display: block;
  text-align: center;
  font-size: 0.75rem;
  color: #9ca3af;
  margin-bottom: 0.25rem;
}

.layout-inspector__spacing-input {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.25rem 0.375rem;
  font-size: 0.875rem;
  text-align: center;
  color: #1f2937;
  background: #fff;
}

.layout-inspector__spacing-input:focus {
  outline: none;
  border-color: var(--c-primary-400, #818cf8);
  box-shadow: 0 0 0 1px var(--c-primary-400, #818cf8);
}

html.dark .layout-inspector { color: rgb(229 231 235); }
html.dark .layout-inspector input,
html.dark .layout-inspector select { background: rgb(17 24 39); color: rgb(229 231 235); border-color: rgb(75 85 99); }
</style>
