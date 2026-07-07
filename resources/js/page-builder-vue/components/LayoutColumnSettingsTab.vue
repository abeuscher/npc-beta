<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useEditorStore } from '../stores/editor'

type Layout = NonNullable<ReturnType<typeof useEditorStore>['selectedLayout']>

const props = defineProps<{ layout: Layout }>()

const store = useEditorStore()

function setLayoutConfigKey(key: string, value: any) {
  store.updateLocalLayout(props.layout.id, {
    layout_config: { [key]: value },
  })
}

const contentFullWidth = computed(() => !!props.layout.layout_config?.content_full_width)
const backgroundFullWidth = computed(() => !!props.layout.layout_config?.background_full_width)
// Concrete-value default: absent or true → checked; only an explicit false
// opts out. Matches PageBlockRenderer + LayoutRegion's read path.
const collapseMobile = computed(() => props.layout.layout_config?.collapse_mobile !== false)
const backgroundDisabled = computed(() => contentFullWidth.value)
const backgroundDisabledReason = computed(() =>
  contentFullWidth.value
    ? 'Background fills page width automatically when content is set to fill page width.'
    : undefined,
)

function setDisplay(display: 'flex' | 'grid') {
  store.updateLocalLayout(props.layout.id, { display })
}

function onColumnsChange(e: Event) {
  const input = e.target as HTMLInputElement
  const value = parseInt(input.value, 10)
  if (isNaN(value) || value < 1 || value > 12) return

  // Decreasing the column count drops the slots at indices >= the new count;
  // any widgets in them stop rendering on the page. Confirm before hiding
  // populated columns so content doesn't vanish as a side effect of an
  // unrelated change (mirrors the delete-layout confirm in LayoutInspectorPanel).
  const current = props.layout.columns ?? 1
  if (value < current) {
    const slots = (props.layout.slots ?? {}) as Record<string | number, unknown[]>
    let hidden = 0
    for (let i = value; i < current; i++) {
      hidden += (slots[i] ?? slots[String(i)] ?? []).length
    }
    if (hidden > 0) {
      const w = hidden === 1 ? 'widget' : 'widgets'
      const it = hidden === 1 ? 'it' : 'them'
      const ok = window.confirm(
        `Reducing the column count will hide ${hidden} ${w} in the dropped column${current - value === 1 ? '' : 's'} — ${it} will no longer appear on the page. Move ${it} into another column first to keep ${it} visible.\n\nReduce the column count anyway?`
      )
      if (!ok) {
        input.value = String(current)
        return
      }
    }
  }

  const config = { ...(props.layout.layout_config ?? {}) }
  if (props.layout.display === 'grid') {
    const cur = (config.grid_template_columns ?? '').toString().trim().split(/\s+/).filter(Boolean)
    while (cur.length < value) cur.push('1fr')
    cur.length = value
    config.grid_template_columns = cur.join(' ')
  }
  if (props.layout.display === 'flex') {
    const basis = Array.isArray(config.flex_basis) ? [...config.flex_basis] : []
    while (basis.length < value) basis.push('auto')
    basis.length = value
    config.flex_basis = basis
  }

  store.updateLocalLayout(props.layout.id, { columns: value, layout_config: config })
}

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
  () => props.layout.layout_config?.grid_template_columns ?? ''
)

const gridTemplateMode = ref<'preset' | 'manual'>('preset')

watch(
  () => props.layout,
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
  store.updateLocalLayout(props.layout.id, {
    layout_config: { grid_template_columns: value },
  })
}

const gapPresets = ['0', '0.5rem', '1rem', '1.5rem', '2rem', '3rem']

// Alignment presets (session 363, the s317 owner-settled shape): a macro
// dropdown that resolves to the same underlying values as the raw CSS selects
// below it — the raw controls stay for fine-tuning.
const gridAlignmentPresets = [
  { label: 'Fill cells (default)',  align_items: 'stretch', justify_items: 'stretch' },
  { label: 'Top-align content',     align_items: 'start',   justify_items: 'stretch' },
  { label: 'Center content',        align_items: 'center',  justify_items: 'center' },
  { label: 'Bottom-align content',  align_items: 'end',     justify_items: 'stretch' },
]

const flexAlignmentPresets = [
  { label: 'Default (start, full height)', justify_content: 'flex-start',    align_items: 'stretch' },
  { label: 'Center content',               justify_content: 'center',        align_items: 'center' },
  { label: 'Top-align columns',            justify_content: 'flex-start',    align_items: 'flex-start' },
  { label: 'Space between columns',        justify_content: 'space-between', align_items: 'stretch' },
  { label: 'Distribute evenly',            justify_content: 'space-evenly',  align_items: 'stretch' },
]

function applyGridAlignmentPreset(e: Event) {
  const select = e.target as HTMLSelectElement
  const preset = gridAlignmentPresets[parseInt(select.value, 10)]
  if (!preset) return
  store.updateLocalLayout(props.layout.id, {
    layout_config: { align_items: preset.align_items, justify_items: preset.justify_items },
  })
  select.value = ''
}

function applyFlexAlignmentPreset(e: Event) {
  const select = e.target as HTMLSelectElement
  const preset = flexAlignmentPresets[parseInt(select.value, 10)]
  if (!preset) return
  store.updateLocalLayout(props.layout.id, {
    layout_config: { justify_content: preset.justify_content, align_items: preset.align_items },
  })
  select.value = ''
}

function setFlexBasis(slotIdx: number, value: string) {
  const cur = Array.isArray(props.layout.layout_config?.flex_basis)
    ? [...(props.layout.layout_config!.flex_basis as string[])]
    : []
  while (cur.length < (props.layout.columns ?? 0)) cur.push('auto')
  cur[slotIdx] = value
  store.updateLocalLayout(props.layout.id, {
    layout_config: { flex_basis: cur },
  })
}

function getFlexBasis(slotIdx: number): string {
  const arr = (props.layout.layout_config?.flex_basis ?? []) as string[]
  return arr[slotIdx] ?? 'auto'
}
</script>

<template>
  <div class="layout-column-settings">
    <!-- Full width at the top — layout-behavior controls -->
    <div class="layout-inspector__field">
      <label class="layout-inspector__checkbox-row">
        <input
          type="checkbox"
          :checked="contentFullWidth"
          class="layout-inspector__checkbox"
          @change="setLayoutConfigKey('content_full_width', ($event.target as HTMLInputElement).checked)"
        >
        <span>Content fills page width</span>
      </label>
      <label
        class="layout-inspector__checkbox-row"
        :class="{ 'layout-inspector__checkbox-row--disabled': backgroundDisabled }"
        :title="backgroundDisabledReason"
      >
        <input
          type="checkbox"
          :checked="backgroundFullWidth || contentFullWidth"
          :disabled="backgroundDisabled"
          class="layout-inspector__checkbox"
          @change="setLayoutConfigKey('background_full_width', ($event.target as HTMLInputElement).checked)"
        >
        <span>Background fills page width</span>
      </label>
      <p v-if="backgroundDisabled" class="layout-inspector__hint layout-inspector__hint--disabled">
        {{ backgroundDisabledReason }}
      </p>
      <label class="layout-inspector__checkbox-row">
        <input
          type="checkbox"
          :checked="collapseMobile"
          class="layout-inspector__checkbox"
          @change="setLayoutConfigKey('collapse_mobile', ($event.target as HTMLInputElement).checked)"
        >
        <span>Collapse columns on mobile</span>
      </label>
      <p class="layout-inspector__hint">When content is off, column tracks are constrained to the site content container. Collapse stacks the columns into one at narrow widths (≤768px); turn it off for layouts meant to stay side-by-side at every width (e.g. small logo + nav bars).</p>
    </div>

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
        <label class="layout-inspector__label-row" for="layout-grid-alignment-preset">Alignment preset</label>
        <select
          id="layout-grid-alignment-preset"
          class="layout-inspector__input"
          value=""
          @change="applyGridAlignmentPreset"
        >
          <option value="">Choose a preset…</option>
          <option v-for="(preset, i) in gridAlignmentPresets" :key="preset.label" :value="i">
            {{ preset.label }}
          </option>
        </select>
        <p class="layout-inspector__hint">Sets align-items and justify-items together — fine-tune with the controls below.</p>
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
        <label class="layout-inspector__label-row" for="layout-flex-alignment-preset">Alignment preset</label>
        <select
          id="layout-flex-alignment-preset"
          class="layout-inspector__input"
          value=""
          @change="applyFlexAlignmentPreset"
        >
          <option value="">Choose a preset…</option>
          <option v-for="(preset, i) in flexAlignmentPresets" :key="preset.label" :value="i">
            {{ preset.label }}
          </option>
        </select>
        <p class="layout-inspector__hint">Sets justify-content and align-items together — fine-tune with the controls below.</p>
      </div>

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
</template>

<style scoped>
.layout-column-settings {
  display: contents;
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

/* Reason a control is disabled, shown inline under the faded checkbox so the
   "why" no longer lives only in a hover tooltip. Indented to sit under the
   checkbox label, with a touch more emphasis than a plain field hint. */
.layout-inspector__hint--disabled {
  margin: 0.25rem 0 0 1.5rem;
  color: #6b7280;
  font-style: italic;
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

.layout-inspector__checkbox-row--disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.layout-inspector__checkbox {
  border-radius: 0.25rem;
  border: 1px solid #d1d5db;
  color: var(--c-primary-600, #4f46e5);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

html.dark .layout-column-settings input,
html.dark .layout-column-settings select { background: rgb(17 24 39); color: rgb(229 231 235); border-color: rgb(75 85 99); }
</style>
