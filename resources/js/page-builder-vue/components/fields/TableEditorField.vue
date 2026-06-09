<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref } from 'vue'
import { Bold, Italic, Link as LinkIcon } from 'lucide-vue-next'
import type { FieldDef, Widget } from '../../types'
import { useEditorStore } from '../../stores/editor'
import { createTableEditor, type TableEditorHandle } from '../../prosemirror-table/editor'
import {
  createTable,
  addRowAfter,
  deleteRow,
  addColumnAfter,
  deleteColumn,
  mergeCells,
  splitCell,
  toggleHeaderRow,
  deleteTable,
  toggleBold,
  toggleItalic,
  setLink,
} from '../../prosemirror-table/commands'

const props = defineProps<{
  field: FieldDef
  widget: Widget
  modelValue: any
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const store = useEditorStore()

const open = ref(false)
const html = ref<string>(typeof props.modelValue === 'string' ? props.modelValue : '')
const mountEl = ref<HTMLElement | null>(null)
let editor: TableEditorHandle | null = null

const hasTable = computed(() => html.value.trim() !== '')

// ── Per-column widths ────────────────────────────────────────────────────────
function readWidths(): number[] {
  const raw = props.widget.config?.column_widths ?? props.widget.resolved_defaults?.column_widths
  if (!Array.isArray(raw)) return []
  return raw.map((x: any) => (typeof x === 'number' ? x : parseInt(x, 10) || 0))
}
const widths = ref<number[]>(readWidths())
const columnCount = ref(0)

const colTemplate = computed(() =>
  widths.value.map((w) => (w >= 1 && w <= 100 ? `${w}%` : '1fr')).join(' ')
)

function persistWidths(): void {
  store.updateLocalConfig(props.widget.id, 'column_widths', widths.value.slice())
}

function reconcileColumns(): void {
  if (!editor) return
  const count = editor.getColumnCount()
  columnCount.value = count
  const next = widths.value.slice(0, count)
  while (next.length < count) next.push(0)
  const changed = next.length !== widths.value.length || next.some((w, i) => w !== widths.value[i])
  widths.value = next
  if (changed) persistWidths()
  editor.setColumnWidths(widths.value)
}

function setWidth(i: number, raw: string): void {
  const n = parseInt(raw, 10)
  const v = Number.isFinite(n) && n >= 1 && n <= 100 ? n : 0
  const next = widths.value.slice()
  next[i] = v
  widths.value = next
  persistWidths()
  editor?.setColumnWidths(widths.value)
}

// ── WYSIWYG preview styling (mirrors template.blade.php) ─────────────────────
function cfg(key: string): any {
  const c = props.widget.config
  if (c && c[key] !== undefined) return c[key]
  return props.widget.resolved_defaults?.[key]
}
function hex(v: any, fallback: string): string {
  return typeof v === 'string' && /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(v) ? v : fallback
}
function hAlign(v: string): string {
  for (const h of ['left', 'center', 'right']) if (v.includes(h)) return h
  return 'left'
}
function vAlign(v: string): string {
  if (v.includes('top')) return 'top'
  if (v.includes('bottom')) return 'bottom'
  return 'middle'
}

const previewStyle = computed(() => {
  const border = (cfg('border') ?? {}) as Record<string, any>
  const w = Math.max(0, parseInt(border.width, 10) || 0)
  const c = hex(border.color, '#cbd5e1')
  const zebra = !!cfg('zebra')
  const style: Record<string, string> = {
    '--np-table-header-bg': hex(cfg('header_bg'), '#f1f5f9'),
    '--np-table-header-text': hex(cfg('header_text'), '#0f172a'),
    '--np-table-body-bg': hex(cfg('body_bg'), '#ffffff'),
    '--np-table-body-text': hex(cfg('body_text'), '#1f2937'),
    '--np-table-border-w': `${w}px`,
    '--np-table-border-c': c,
    '--np-table-header-align': hAlign(String(cfg('header_align') ?? 'center')),
    '--np-table-header-valign': vAlign(String(cfg('header_align') ?? 'center')),
    '--np-table-body-align': hAlign(String(cfg('body_align') ?? 'middle-left')),
    '--np-table-body-valign': vAlign(String(cfg('body_align') ?? 'middle-left')),
  }
  if (zebra) {
    style['--np-table-zebra-bg'] = hex(cfg('zebra_bg'), '#f8fafc')
    style['--np-table-zebra-text'] = hex(cfg('zebra_text'), '#1f2937')
  }
  // Outer frame edges (matches composeBorderProps on the public box).
  if (border.top) style.borderTop = `${w}px solid ${c}`
  if (border.right) style.borderRight = `${w}px solid ${c}`
  if (border.bottom) style.borderBottom = `${w}px solid ${c}`
  if (border.left) style.borderLeft = `${w}px solid ${c}`
  return style
})

const previewClasses = computed(() => {
  const border = (cfg('border') ?? {}) as Record<string, any>
  return {
    'np-pm--zebra': !!cfg('zebra'),
    'np-pm--inner-h': !!border.inner_horizontal,
    'np-pm--inner-v': !!border.inner_vertical,
  }
})

// ── Grid-picker / link state ─────────────────────────────────────────────────
const MAX_GRID = 8
const hoverRows = ref(0)
const hoverCols = ref(0)
const linkOpen = ref(false)
const linkUrl = ref('')

async function openEditor(): Promise<void> {
  open.value = true
  await nextTick()
  if (!mountEl.value) return
  editor = createTableEditor(mountEl.value, {
    html: html.value,
    columnWidths: widths.value,
    onChange(next) {
      html.value = next
      emit('update:modelValue', next)
      reconcileColumns()
    },
  })
  reconcileColumns()
  editor.view.focus()
}

function closeEditor(): void {
  destroyEditor()
  open.value = false
  linkOpen.value = false
}

function destroyEditor(): void {
  editor?.destroy()
  editor = null
}

function run(command: (state: any, dispatch: any) => boolean): void {
  if (!editor) return
  command(editor.view.state, editor.view.dispatch)
  editor.view.focus()
}

function insertTable(rows: number, cols: number): void {
  if (rows < 1 || cols < 1) return
  run(createTable(rows, cols, true))
  hoverRows.value = 0
  hoverCols.value = 0
}

function applyLink(): void {
  run(setLink(linkUrl.value))
  linkUrl.value = ''
  linkOpen.value = false
}

function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape') {
    closeEditor()
  }
}

onUnmounted(destroyEditor)
</script>

<template>
  <div class="table-field">
    <button
      type="button"
      class="table-field__open"
      @click="openEditor"
    >{{ hasTable ? 'Edit table' : 'Insert a table' }}</button>

    <div
      v-if="hasTable"
      class="table-field__preview"
      v-html="html"
    />
    <p v-else class="inspector-hint inspector-hint--italic">No table yet.</p>

    <Teleport to="body">
      <Transition name="modal-fade">
        <div
          v-if="open"
          class="table-editor-overlay"
          @click.self="closeEditor"
          @keydown="onKeydown"
        >
          <div class="table-editor-modal" role="dialog" aria-label="Edit table">
            <div class="table-editor-modal__head">
              <p class="table-editor-modal__title">Table</p>
              <button
                type="button"
                class="table-editor-modal__close"
                aria-label="Close table editor"
                @click="closeEditor"
              >×</button>
            </div>

            <div class="table-editor-modal__toolbar">
              <template v-if="!hasTable">
                <div class="table-grid-picker">
                  <p class="table-grid-picker__label">
                    {{ hoverRows > 0 ? `${hoverCols} × ${hoverRows}` : 'Pick a size' }}
                  </p>
                  <div class="table-grid-picker__grid" @mouseleave="hoverRows = 0; hoverCols = 0">
                    <template v-for="r in MAX_GRID" :key="r">
                      <button
                        v-for="c in MAX_GRID"
                        :key="`${r}-${c}`"
                        type="button"
                        class="table-grid-picker__cell"
                        :class="{ 'table-grid-picker__cell--on': r <= hoverRows && c <= hoverCols }"
                        :aria-label="`Insert ${c} by ${r} table`"
                        @mouseenter="hoverRows = r; hoverCols = c"
                        @click="insertTable(r, c)"
                      />
                    </template>
                  </div>
                </div>
              </template>

              <template v-else>
                <div class="table-editor-toolbar__group">
                  <button type="button" class="table-editor-toolbar__btn" title="Add row below" @click="run(addRowAfter)">+ Row</button>
                  <button type="button" class="table-editor-toolbar__btn" title="Delete row" @click="run(deleteRow)">− Row</button>
                  <button type="button" class="table-editor-toolbar__btn" title="Add column right" @click="run(addColumnAfter)">+ Col</button>
                  <button type="button" class="table-editor-toolbar__btn" title="Delete column" @click="run(deleteColumn)">− Col</button>
                </div>
                <div class="table-editor-toolbar__group">
                  <button type="button" class="table-editor-toolbar__btn" title="Merge selected cells" @click="run(mergeCells)">Merge</button>
                  <button type="button" class="table-editor-toolbar__btn" title="Split cell" @click="run(splitCell)">Split</button>
                  <button type="button" class="table-editor-toolbar__btn" title="Toggle header row" @click="run(toggleHeaderRow)">Header</button>
                </div>
                <div class="table-editor-toolbar__group">
                  <button type="button" class="table-editor-toolbar__btn" title="Bold" @click="run(toggleBold)"><Bold :size="15" /></button>
                  <button type="button" class="table-editor-toolbar__btn" title="Italic" @click="run(toggleItalic)"><Italic :size="15" /></button>
                  <button type="button" class="table-editor-toolbar__btn" :class="{ 'table-editor-toolbar__btn--active': linkOpen }" title="Link" @click="linkOpen = !linkOpen"><LinkIcon :size="15" /></button>
                </div>
                <div class="table-editor-toolbar__group table-editor-toolbar__group--end">
                  <button type="button" class="table-editor-toolbar__btn table-editor-toolbar__btn--danger" title="Delete table" @click="run(deleteTable)">Delete table</button>
                </div>
              </template>
            </div>

            <div v-if="linkOpen && hasTable" class="table-editor-modal__link">
              <input
                v-model="linkUrl"
                type="url"
                class="inspector-control"
                placeholder="https://example.com"
                @keydown.enter.prevent="applyLink"
              >
              <button type="button" class="table-editor-toolbar__btn" @click="applyLink">Apply</button>
            </div>

            <div class="table-editor-modal__body">
              <div
                v-if="hasTable && columnCount > 0"
                class="table-col-widths"
                :style="{ gridTemplateColumns: colTemplate }"
              >
                <div v-for="(w, i) in widths" :key="i" class="table-col-widths__cell">
                  <input
                    type="number"
                    min="0"
                    max="100"
                    class="table-col-widths__input"
                    :value="w >= 1 ? w : ''"
                    placeholder="auto"
                    :aria-label="`Column ${i + 1} width percent`"
                    @input="setWidth(i, ($event.target as HTMLInputElement).value)"
                  >
                  <span class="table-col-widths__pct">%</span>
                </div>
              </div>
              <div
                ref="mountEl"
                class="table-editor-modal__pm"
                :class="previewClasses"
                :style="previewStyle"
              ></div>
            </div>

            <div class="table-editor-modal__foot">
              <button type="button" class="table-editor-modal__done" @click="closeEditor">Done</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.table-field__open {
  display: inline-block;
  padding: 0.4rem 0.75rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #2563eb;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: var(--np-control-radius, 0.375rem);
  cursor: pointer;
}

.table-field__open:hover {
  background: #dbeafe;
}

.table-field__preview {
  margin-top: 0.5rem;
  max-height: 180px;
  overflow: auto;
  padding: 0.25rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.375rem;
  font-size: 0.75rem;
}

.table-field__preview :deep(table) {
  width: 100%;
  border-collapse: collapse;
}

.table-field__preview :deep(td),
.table-field__preview :deep(th) {
  border: 1px solid #d1d5db;
  padding: 0.25rem 0.4rem;
}

/* Modal — mirrors ConfirmDeleteModal's overlay pattern, wider for the editor. */
.table-editor-overlay {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.5);
}

.table-editor-modal {
  display: flex;
  flex-direction: column;
  width: min(900px, 95vw);
  max-height: 90vh;
  background: #fff;
  border-radius: 0.75rem;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.table-editor-modal__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #e5e7eb;
}

.table-editor-modal__title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 600;
  color: #111827;
}

.table-editor-modal__close {
  border: none;
  background: none;
  font-size: 1.25rem;
  line-height: 1;
  color: #6b7280;
  cursor: pointer;
}

.table-editor-modal__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
}

.table-editor-toolbar__group {
  display: flex;
  gap: 0.25rem;
  padding-right: 0.5rem;
  border-right: 1px solid #e5e7eb;
}

.table-editor-toolbar__group--end {
  border-right: none;
  margin-left: auto;
}

.table-editor-toolbar__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  min-height: 1.75rem;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  color: #374151;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  cursor: pointer;
}

.table-editor-toolbar__btn:hover {
  background: #f3f4f6;
}

.table-editor-toolbar__btn--active {
  border-color: #2563eb;
  color: #2563eb;
}

.table-editor-toolbar__btn--danger {
  color: #b91c1c;
  border-color: #fecaca;
}

.table-editor-toolbar__btn--danger:hover {
  background: #fef2f2;
}

.table-editor-modal__link {
  display: flex;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-bottom: 1px solid #e5e7eb;
}

.table-editor-modal__body {
  flex: 1;
  overflow: auto;
  padding: 1rem;
}

.table-editor-modal__foot {
  display: flex;
  justify-content: flex-end;
  padding: 0.75rem 1rem;
  border-top: 1px solid #e5e7eb;
}

.table-editor-modal__done {
  padding: 0.5rem 1.25rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #fff;
  background: #2563eb;
  border: none;
  border-radius: 0.5rem;
  cursor: pointer;
}

/* Per-column width inputs — a grid mirroring the table's column template so
 * each field sits above its column. */
.table-col-widths {
  display: grid;
  gap: 2px;
  margin-bottom: 0.5rem;
}

.table-col-widths__cell {
  display: flex;
  align-items: center;
  gap: 0.125rem;
  min-width: 0;
}

.table-col-widths__input {
  width: 100%;
  min-width: 0;
  height: 1.5rem;
  padding: 0.125rem 0.25rem;
  font-size: 0.6875rem;
  text-align: center;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  background: #fff;
  -moz-appearance: textfield;
  appearance: textfield;
}

.table-col-widths__input::-webkit-outer-spin-button,
.table-col-widths__input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.table-col-widths__pct {
  font-size: 0.625rem;
  color: #9ca3af;
}

/* Grid picker */
.table-grid-picker {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.table-grid-picker__label {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #374151;
}

.table-grid-picker__grid {
  display: grid;
  grid-template-columns: repeat(8, 1.25rem);
  grid-auto-rows: 1.25rem;
  gap: 2px;
}

.table-grid-picker__cell {
  width: 1.25rem;
  height: 1.25rem;
  padding: 0;
  border: 1px solid #d1d5db;
  background: #fff;
  cursor: pointer;
}

.table-grid-picker__cell--on {
  background: #bfdbfe;
  border-color: #2563eb;
}

/* ProseMirror surface — WYSIWYG: colours, alignment, and gridlines come from
 * the widget config via the --np-table-* custom properties + np-pm-- classes
 * set on this element, mirroring the public render. A faint dashed guide keeps
 * empty/borderless cells editable. */
.table-editor-modal__pm :deep(.ProseMirror) {
  min-height: 200px;
  outline: none;
}

.table-editor-modal__pm :deep(table) {
  border-collapse: collapse;
  margin: 0;
  width: 100%;
}

.table-editor-modal__pm :deep(th),
.table-editor-modal__pm :deep(td) {
  padding: 0.4rem 0.6rem;
  position: relative;
  outline: 1px dashed rgba(148, 163, 184, 0.4);
  outline-offset: -1px;
}

.table-editor-modal__pm :deep(th) {
  background: var(--np-table-header-bg);
  color: var(--np-table-header-text);
  text-align: var(--np-table-header-align);
  vertical-align: var(--np-table-header-valign);
  font-weight: 600;
}

.table-editor-modal__pm :deep(td) {
  background: var(--np-table-body-bg);
  color: var(--np-table-body-text);
  text-align: var(--np-table-body-align);
  vertical-align: var(--np-table-body-valign);
}

.table-editor-modal__pm.np-pm--zebra :deep(tbody tr:nth-child(even) td) {
  background: var(--np-table-zebra-bg);
  color: var(--np-table-zebra-text);
}

.table-editor-modal__pm.np-pm--inner-h :deep(tbody tr + tr > *) {
  border-top: var(--np-table-border-w) solid var(--np-table-border-c);
}

.table-editor-modal__pm.np-pm--inner-v :deep(tbody tr > * + *) {
  border-left: var(--np-table-border-w) solid var(--np-table-border-c);
}

.table-editor-modal__pm :deep(.selectedCell) {
  background: rgba(37, 99, 235, 0.12);
}

.table-editor-modal__pm :deep(p) {
  margin: 0;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.15s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
