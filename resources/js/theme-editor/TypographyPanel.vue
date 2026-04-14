<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useTypographyStore } from './stores/typography'
import FontInput, { type FontValue } from '../page-builder-vue/components/primitives/FontInput.vue'
import SpacingInput, { type SpacingValue } from '../page-builder-vue/components/primitives/SpacingInput.vue'
import type { ElementKey, TypographyBootstrap } from './types'

const props = defineProps<{ bootstrap: TypographyBootstrap }>()

const store = useTypographyStore()

onMounted(() => store.init(props.bootstrap))

const state = computed(() => store.state)
const families = computed(() => props.bootstrap.families)

const headingElements: ElementKey[] = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']
const bodyElements: ElementKey[] = ['p', 'ul_li', 'ol_li']

const elementLabels: Record<ElementKey, string> = {
  h1: 'Heading 1', h2: 'Heading 2', h3: 'Heading 3',
  h4: 'Heading 4', h5: 'Heading 5', h6: 'Heading 6',
  p: 'Paragraph',
  ul_li: 'Unordered list item',
  ol_li: 'Ordered list item',
}

function inheritBucketFor(el: ElementKey): { family: string | null, label: string } {
  if (!state.value) return { family: null, label: '' }
  if (headingElements.includes(el)) {
    return { family: state.value.buckets.heading_family, label: 'Heading' }
  }
  return { family: state.value.buckets.body_family, label: 'Body' }
}

function applyInheritedFamily(el: ElementKey) {
  if (!state.value) return
  const inherit = inheritBucketFor(el)
  if (!inherit.family) return
  state.value.elements[el].font.family = inherit.family
  store.queueSave()
}

function isInheritingFamily(el: ElementKey): boolean {
  if (!state.value) return false
  const inherit = inheritBucketFor(el)
  return !!inherit.family && state.value.elements[el].font.family === inherit.family
}

function previewStyle(el: ElementKey): Record<string, string> {
  if (!state.value) return {}
  const cfg = state.value.elements[el]
  const style: Record<string, string> = {}
  style.fontFamily = cfg.font.family
  style.fontWeight = cfg.font.weight
  style.fontSize = `${cfg.font.size.value}${cfg.font.size.unit}`
  style.lineHeight = String(cfg.font.line_height)
  style.letterSpacing = `${cfg.font.letter_spacing.value}${cfg.font.letter_spacing.unit}`
  if (cfg.font.case === 'small-caps') style.fontVariant = 'small-caps'
  else style.textTransform = cfg.font.case
  for (const side of ['top', 'right', 'bottom', 'left'] as const) {
    const m = cfg.margin?.[side]
    const p = cfg.padding?.[side]
    style[`margin${capitalize(side)}`] = `${m ?? 0}px`
    style[`padding${capitalize(side)}`] = `${p ?? 0}px`
  }
  return style
}

function listStyleFor(el: 'ul_li' | 'ol_li'): Record<string, string> {
  const cfg = state.value?.elements[el]
  return cfg?.list_style_type ? { listStyleType: cfg.list_style_type } : {}
}

function capitalize(s: string): string {
  return s.charAt(0).toUpperCase() + s.slice(1)
}

function onBucketChange(bucket: 'heading_family' | 'body_family' | 'nav_family', value: string) {
  if (!state.value) return
  const previous = state.value.buckets[bucket]
  const next = value || null
  state.value.buckets[bucket] = next

  // Cascade: any element whose family currently matches the previous bucket value
  // follows the bucket to its new value. Elements overridden to something else
  // are left alone. Only runs when both previous and next are concrete strings.
  if (previous && next) {
    const targets: ElementKey[] =
      bucket === 'heading_family' ? headingElements :
      bucket === 'body_family'    ? bodyElements :
      []
    for (const el of targets) {
      if (state.value.elements[el].font.family === previous) {
        state.value.elements[el].font.family = next
      }
    }
  }

  store.queueSave()
}

function onSampleTextChange(value: string) {
  if (!state.value) return
  state.value.sample_text = value
  store.queueSave()
}

function onFontChange(el: ElementKey, value: FontValue) {
  if (!state.value) return
  state.value.elements[el].font = value
  store.queueSave()
}

function onSpacingChange(el: ElementKey, box: 'margin' | 'padding', value: SpacingValue) {
  if (!state.value) return
  state.value.elements[el][box] = value
  store.queueSave()
}

function onListStyleChange(el: 'ul_li' | 'ol_li', value: string) {
  if (!state.value) return
  state.value.elements[el].list_style_type = value
  store.queueSave()
}

function onMarkerColorChange(el: 'ul_li' | 'ol_li', value: string) {
  if (!state.value) return
  state.value.elements[el].marker_color = value || null
  store.queueSave()
}

const listStyleOptions = [
  { value: 'disc',    label: 'Disc' },
  { value: 'circle',  label: 'Circle' },
  { value: 'square',  label: 'Square' },
  { value: 'decimal', label: 'Decimal' },
  { value: 'lower-alpha', label: 'Lower alpha' },
  { value: 'upper-alpha', label: 'Upper alpha' },
  { value: 'lower-roman', label: 'Lower roman' },
  { value: 'upper-roman', label: 'Upper roman' },
  { value: 'none',    label: 'None' },
]

const elementOrder: ElementKey[] = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'ul_li', 'ol_li']

const expanded = ref<Record<ElementKey, boolean>>({
  h1: false, h2: false, h3: false, h4: false, h5: false, h6: false,
  p: false, ul_li: false, ol_li: false,
})

function toggle(el: ElementKey) {
  expanded.value[el] = !expanded.value[el]
}

function downloadScss() {
  window.location.href = props.bootstrap.exportUrl
}
</script>

<template>
  <div v-if="state" class="theme-typography">
    <div class="theme-typography__topbar">
      <div class="theme-typography__sample">
        <label class="theme-typography__label">Sample text</label>
        <input
          type="text"
          class="theme-typography__input"
          :value="state.sample_text"
          @input="onSampleTextChange(($event.target as HTMLInputElement).value)"
        >
      </div>
      <div class="theme-typography__status">
        <span v-if="store.saving">Saving…</span>
        <span v-else-if="store.saveError" class="theme-typography__error">{{ store.saveError }}</span>
        <span v-else-if="store.lastSavedAt">Saved</span>
        <button type="button" class="theme-typography__export" @click="downloadScss">Download SCSS</button>
      </div>
    </div>

    <section class="theme-typography__section">
      <h3 class="theme-typography__section-title">Families</h3>
      <div class="theme-typography__bucket-row">
        <div class="theme-typography__bucket">
          <label class="theme-typography__label">Heading</label>
          <select
            class="theme-typography__input"
            :value="state.buckets.heading_family ?? ''"
            @change="onBucketChange('heading_family', ($event.target as HTMLSelectElement).value)"
          >
            <option value="">— unset —</option>
            <option v-for="f in families" :key="f.value" :value="f.value">{{ f.label }}</option>
          </select>
        </div>
        <div class="theme-typography__bucket">
          <label class="theme-typography__label">Body</label>
          <select
            class="theme-typography__input"
            :value="state.buckets.body_family ?? ''"
            @change="onBucketChange('body_family', ($event.target as HTMLSelectElement).value)"
          >
            <option value="">— unset —</option>
            <option v-for="f in families" :key="f.value" :value="f.value">{{ f.label }}</option>
          </select>
        </div>
        <div class="theme-typography__bucket">
          <label class="theme-typography__label">Nav</label>
          <select
            class="theme-typography__input"
            :value="state.buckets.nav_family ?? ''"
            @change="onBucketChange('nav_family', ($event.target as HTMLSelectElement).value)"
          >
            <option value="">— unset —</option>
            <option v-for="f in families" :key="f.value" :value="f.value">{{ f.label }}</option>
          </select>
        </div>
      </div>
    </section>

    <section class="theme-typography__section">
      <h3 class="theme-typography__section-title">Elements</h3>
      <div
        v-for="el in elementOrder"
        :key="el"
        class="theme-typography__element"
        :class="{ 'theme-typography__element--open': expanded[el] }"
      >
        <div
          class="theme-typography__element-header"
          :aria-expanded="expanded[el]"
          @click="toggle(el)"
        >
          <span class="theme-typography__element-label">{{ elementLabels[el] }}</span>
          <div class="theme-typography__element-preview" :style="previewStyle(el)">
            <template v-if="el === 'ul_li'">
              <ul class="theme-typography__preview-list-inline" :style="listStyleFor('ul_li')"><li>{{ state.sample_text }}</li></ul>
            </template>
            <template v-else-if="el === 'ol_li'">
              <ol class="theme-typography__preview-list-inline" :style="listStyleFor('ol_li')"><li>{{ state.sample_text }}</li></ol>
            </template>
            <template v-else>
              <component :is="el" class="theme-typography__preview-inline">{{ state.sample_text }}</component>
            </template>
          </div>
          <button
            v-if="inheritBucketFor(el).family"
            type="button"
            class="theme-typography__inherit"
            :disabled="isInheritingFamily(el)"
            :title="isInheritingFamily(el)
              ? `Already using ${inheritBucketFor(el).label} family`
              : `Use ${inheritBucketFor(el).label} family`"
            @click.stop="applyInheritedFamily(el)"
          >Use {{ inheritBucketFor(el).label }}</button>
          <span class="theme-typography__chevron" aria-hidden="true">{{ expanded[el] ? '▾' : '▸' }}</span>
        </div>

        <div v-if="expanded[el]" class="theme-typography__element-body">
          <div class="theme-typography__controls">
            <FontInput
              :model-value="state.elements[el].font"
              :families="families"
              @update:model-value="onFontChange(el, $event)"
            />
            <div class="theme-typography__spacing-row">
              <SpacingInput
                label="Margin"
                unit="px"
                :model-value="state.elements[el].margin"
                @update:model-value="onSpacingChange(el, 'margin', $event)"
              />
              <SpacingInput
                label="Padding"
                unit="px"
                :model-value="state.elements[el].padding"
                @update:model-value="onSpacingChange(el, 'padding', $event)"
              />
            </div>

            <div v-if="el === 'ul_li' || el === 'ol_li'" class="theme-typography__list-controls">
              <div>
                <label class="theme-typography__label">List style</label>
                <select
                  class="theme-typography__input"
                  :value="state.elements[el].list_style_type ?? ''"
                  @change="onListStyleChange(el, ($event.target as HTMLSelectElement).value)"
                >
                  <option v-for="o in listStyleOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </div>
              <div>
                <label class="theme-typography__label">Marker color</label>
                <input
                  type="color"
                  class="theme-typography__input theme-typography__input--color"
                  :value="state.elements[el].marker_color ?? '#000000'"
                  @input="onMarkerColorChange(el, ($event.target as HTMLInputElement).value)"
                >
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.theme-typography {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  font-size: 0.875rem;
  color: #1f2937;
}

.theme-typography__topbar {
  display: flex;
  gap: 1rem;
  align-items: flex-end;
  justify-content: space-between;
}

.theme-typography__sample {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  flex: 1 1 auto;
  max-width: 40rem;
}

.theme-typography__status {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.75rem;
  color: #6b7280;
}

.theme-typography__error {
  color: #b91c1c;
}

.theme-typography__export {
  padding: 0.375rem 0.75rem;
  border-radius: 0.375rem;
  border: 1px solid #d1d5db;
  background: #f9fafb;
  font-size: 0.75rem;
  cursor: pointer;
}

.theme-typography__export:hover {
  background: #f3f4f6;
}

.theme-typography__section {
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1rem;
  background: #ffffff;
}

.theme-typography__section-title {
  margin: 0 0 0.75rem 0;
  font-size: 0.875rem;
  font-weight: 600;
}

.theme-typography__bucket-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

.theme-typography__bucket {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.theme-typography__label {
  font-size: 0.75rem;
  color: #6b7280;
  font-weight: 500;
}

.theme-typography__input {
  width: 100%;
  padding: 0.375rem 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  background: #ffffff;
}

.theme-typography__input--color {
  padding: 0;
  height: 2rem;
}

/* ── Element drawers ──────────────────────────────────────────────── */

.theme-typography__element {
  border-top: 1px solid #f3f4f6;
}

.theme-typography__element:first-of-type {
  border-top: 0;
}

.theme-typography__element-header {
  display: grid;
  grid-template-columns: 10rem minmax(0, 1fr) auto 1rem;
  align-items: center;
  gap: 1rem;
  width: 100%;
  padding: 0.5rem 0.25rem;
  background: transparent;
  border: 0;
  border-radius: 0.25rem;
  cursor: pointer;
  text-align: left;
}

.theme-typography__inherit {
  flex: 0 0 auto;
  font-size: 0.6875rem;
  color: #374151;
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.25rem 0.5rem;
  cursor: pointer;
  white-space: nowrap;
}

.theme-typography__inherit:hover:not(:disabled) {
  background: #e5e7eb;
}

.theme-typography__inherit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.theme-typography__spacing-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.theme-typography__element-header:hover {
  background: #f9fafb;
}

.theme-typography__element--open > .theme-typography__element-header {
  background: #f3f4f6;
}

.theme-typography__element-label {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
}

.theme-typography__element-preview {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}

.theme-typography__preview-inline {
  margin: 0;
  display: inline;
}

.theme-typography__preview-list-inline {
  margin: 0;
  padding-left: 1.25rem;
  list-style-position: inside;
  display: inline-block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
  vertical-align: top;
}

.theme-typography__chevron {
  font-size: 0.75rem;
  color: #9ca3af;
  text-align: right;
}

.theme-typography__element-body {
  padding: 0.75rem 0.25rem 1rem;
}

.theme-typography__controls {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.theme-typography__list-controls {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}
</style>
