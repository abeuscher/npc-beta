<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  AlignCenter,
  AlignJustify,
  AlignLeft,
  AlignRight,
  Bold,
  ChevronDown,
  Highlighter,
  Image as ImageIcon,
  Italic,
  Link as LinkIcon,
  List,
  ListOrdered,
  MoreHorizontal,
  Palette,
  Quote,
  RemoveFormatting,
  Strikethrough,
  Underline,
} from 'lucide-vue-next'
import InlineTextStyleMenu from './inline-toolbar/InlineTextStyleMenu.vue'
import InlineColorPopover from './inline-toolbar/InlineColorPopover.vue'
import InlineLinkPopover from './inline-toolbar/InlineLinkPopover.vue'
import { useEditorStore } from '../stores/editor'
import { useInlineToolbarPosition } from '../composables/useInlineToolbarPosition'
import { useInlineLinkPopover } from '../composables/useInlineLinkPopover'
import { useInlineMediaInsert } from '../composables/useInlineMediaInsert'
import { useInlineToolbarKeyboard } from '../composables/useInlineToolbarKeyboard'
import { HEROICON_TOOLBAR_BUTTON_SVG } from '../../admin/heroicon-blot.js'

// docs/inline-formatting-toolbar-spec.md is the contract for this component.
// Section refs in comments below pin each behaviour rule to its spec rule.

type Tri = 'active' | 'mixed' | 'inactive'

interface FormatState {
  bold: Tri
  italic: Tri
  underline: Tri
  strike: Tri
  list: 'bullet' | 'ordered' | 'mixed' | null
  blockquote: Tri
  header: number | 'mixed' | null
  align: '' | 'center' | 'right' | 'justify' | 'mixed' | null
  link: string | null
  color: string | 'mixed' | null
  background: string | 'mixed' | null
  collapsed: boolean
}

function emptyState(): FormatState {
  return {
    bold: 'inactive', italic: 'inactive', underline: 'inactive', strike: 'inactive',
    list: null, blockquote: 'inactive', header: null, align: null, link: null,
    color: null, background: null, collapsed: true,
  }
}

const store = useEditorStore()
const handle = computed(() => store.activeInlineEditor)

const barEl = ref<HTMLElement | null>(null)
const {
  top, left, onScreen,
  popoverTop, popoverLeft,
  collapseStep, wrapped,
  updatePosition, requestPositionUpdate, measureBar, positionPopover,
} = useInlineToolbarPosition(handle, barEl)
const fadeShown = ref(false)
const reducedMotion = ref(false)
const formatState = ref<FormatState>(emptyState())

const openPopover = ref<null | 'text-style' | 'color' | 'highlight' | 'link' | 'overflow'>(null)
const popoverAnchor = ref<HTMLElement | null>(null)

// Link popover (§G) — state + insert/edit flow live in the composable; the
// InlineLinkPopover sub-component is the view bound to this controller. The
// composable receives the orchestrator's Quill helpers, open-popover state,
// anchoring, and format-state recompute as deps. withQuill/showPopoverAnchored/
// recomputeFormatState are hoisted function declarations, so they resolve here.
const linkCtl = useInlineLinkPopover({
  handle, store, withQuill, openPopover, showPopoverAnchored, recomputeFormatState,
})
const { openLinkPopover, cancelLinkPopover } = linkCtl

// §F7 media insertions (image upload + heroicon embed + their error toast) and
// §K keyboard/a11y (roving tabindex + Alt+F10/Cmd+K) live in their own
// composables; each manages its own listeners/cleanup.
const { openImage, openHeroicon, imageUploading, errorToast } = useInlineMediaInsert({ handle, store, withQuill })
const { focusedIdx, registerBtn, onToolbarKeydown } = useInlineToolbarKeyboard({ handle, openLinkPopover })

// Editor-change subscription cleanup.
let cleanupForHandle: (() => void) | null = null

// Last known Quill range, captured from selection-change. Used by toolbar
// actions to restore the editor's working range before applying format —
// avoids the "format silently no-ops because native selection drifted"
// class of bug, and avoids re-entering Quill's getSelection() during
// editor-change handling (Quill internally calls focus() → setRange() →
// addRange() which warns when its cached lastRange is stale post-insertEmbed).
let savedRange: { index: number; length: number } | null = null

function isMixed(v: any): boolean {
  return Array.isArray(v)
}

function triFor(v: any): Tri {
  if (v === true || (typeof v === 'string' && v) || (typeof v === 'number' && v)) return 'active'
  if (isMixed(v)) return 'mixed'
  return 'inactive'
}

function recomputeFormatState(rangeArg?: { index: number; length: number } | null): void {
  const h = handle.value
  if (!h) return
  const quill = h.quill
  // Prefer the range delivered by selection-change (savedRange) over a
  // live getSelection() — pulling the range during editor-change handling
  // re-enters Quill's focus()/setRange()/addRange() path which warns on a
  // stale cached lastRange (typical right after insertEmbed). getFormat
  // with explicit (index, length) args does NOT call getSelection.
  const range = rangeArg !== undefined ? rangeArg : savedRange
  let fmt: Record<string, any> = {}
  try {
    if (range) {
      fmt = quill.getFormat(range.index, range.length)
    } else {
      fmt = quill.getFormat()
    }
  } catch {
    fmt = {}
  }
  const next: FormatState = {
    bold: triFor(fmt.bold),
    italic: triFor(fmt.italic),
    underline: triFor(fmt.underline),
    strike: triFor(fmt.strike),
    list: (() => {
      const v = fmt.list
      if (v === 'bullet' || v === 'ordered') return v
      if (isMixed(v)) return 'mixed'
      return null
    })(),
    blockquote: triFor(fmt.blockquote),
    header: (() => {
      const v = fmt.header
      if (typeof v === 'number') return v
      if (isMixed(v)) return 'mixed'
      return null
    })(),
    align: (() => {
      const v = fmt.align
      if (typeof v === 'string') return v as any
      if (isMixed(v)) return 'mixed'
      return ''
    })(),
    link: typeof fmt.link === 'string' ? fmt.link : (isMixed(fmt.link) ? null : null),
    color: typeof fmt.color === 'string' ? fmt.color : (isMixed(fmt.color) ? 'mixed' : null),
    background: typeof fmt.background === 'string' ? fmt.background : (isMixed(fmt.background) ? 'mixed' : null),
    collapsed: !range || range.length === 0,
  }
  formatState.value = next
}

function setupForHandle(): void {
  const h = handle.value
  if (!h) return
  const quill = h.quill
  // Reset saved range; selection-change will populate it as soon as the
  // user interacts (or it's already populated from setSelection in
  // activateRich).
  savedRange = null
  // Snapshot the initial selection that useInlineEdit set just before
  // publishing the handle. Wrapped because Quill's getSelection can throw
  // on edge-case states; recomputeFormatState handles range=null fine.
  try { savedRange = quill.getSelection() } catch { savedRange = null }

  // Subscribe to the two granular events instead of the editor-change
  // aggregate so the range comes to us as an argument (no getSelection
  // re-entry → no addRange warning chain).
  const onSelectionChange = (range: any) => {
    if (range) savedRange = range
    recomputeFormatState(range ?? savedRange)
  }
  const onTextChange = () => {
    recomputeFormatState(savedRange)
  }
  quill.on('selection-change', onSelectionChange)
  quill.on('text-change', onTextChange)
  recomputeFormatState(savedRange)

  const ro = new ResizeObserver(() => requestPositionUpdate())
  ro.observe(h.hostEl)

  const onScroll = () => requestPositionUpdate()
  document.addEventListener('scroll', onScroll, true)
  const onWinResize = () => { requestPositionUpdate(); measureBar() }
  window.addEventListener('resize', onWinResize)

  cleanupForHandle = () => {
    try { quill.off('selection-change', onSelectionChange) } catch { /* torn down */ }
    try { quill.off('text-change', onTextChange) } catch { /* torn down */ }
    ro.disconnect()
    document.removeEventListener('scroll', onScroll, true)
    window.removeEventListener('resize', onWinResize)
    savedRange = null
  }

  nextTick(() => {
    updatePosition()
    measureBar()
  })
}

function teardownForHandle(): void {
  cleanupForHandle?.()
  cleanupForHandle = null
  openPopover.value = null
  imageUploading.value = false
  // intentional: leave focusedIdx so re-target keeps focus orientation
}

let prevWidgetId: string | null = null

watch(handle, async (next, prev) => {
  if (!prev && next) {
    setupForHandle()
    prevWidgetId = next.widgetId
    if (reducedMotion.value) {
      fadeShown.value = true
    } else {
      fadeShown.value = false
      await nextTick()
      requestAnimationFrame(() => { fadeShown.value = true })
    }
  } else if (prev && !next) {
    fadeShown.value = false
    setTimeout(() => {
      if (!handle.value) {
        teardownForHandle()
        prevWidgetId = null
      }
    }, reducedMotion.value ? 0 : 90)
  } else if (prev && next) {
    const sameWidget = prevWidgetId === next.widgetId
    if (sameWidget) {
      teardownForHandle()
      setupForHandle()
      requestPositionUpdate()
      prevWidgetId = next.widgetId
    } else {
      fadeShown.value = false
      setTimeout(async () => {
        teardownForHandle()
        setupForHandle()
        prevWidgetId = next.widgetId
        await nextTick()
        requestAnimationFrame(() => { fadeShown.value = true })
      }, reducedMotion.value ? 0 : 90)
    }
  }
})

// ── §F per-control handlers ─────────────────────────────────────────────

// Quill v2's getSelection() can throw mid-operation when the native browser
// selection drifts off the editor (focus moved, native range went stale,
// or the DOM under it changed). Every call site is wrapped so a transient
// failure logs and degrades gracefully instead of freezing the UI on an
// unhandled rejection. The next selection-change repaints the live state.
function withQuill<T>(fn: (q: any) => T): T | undefined {
  const h = handle.value
  if (!h) return undefined
  try {
    return fn(h.quill)
  } catch (e) {
    console.debug('[inline-toolbar] Quill API call failed:', e)
    return undefined
  }
}

// All format handlers below operate on savedRange via formatText/formatLine
// rather than q.format(). q.format() internally calls getSelection(true)
// which calls focus() → setRange() → addRange(); if native selection has
// drifted off the editor (button click can do this even with
// mousedown.preventDefault on some browsers), getRange returns null and
// format() silently no-ops. formatText/formatLine take an explicit range
// so they apply regardless of where the native cursor is.

function clampRange(q: any, r: { index: number; length: number }): { index: number; length: number } {
  const total = q.getLength()
  const idx = Math.max(0, Math.min(r.index, total - 1))
  const lenClamped = Math.max(0, Math.min(r.length, total - 1 - idx))
  return { index: idx, length: lenClamped }
}

// Quill v2's format APIs (formatText/formatLine/format/removeFormat) all
// flow through modify() → selection.update() → selection.getRange() →
// normalizedToRange(), which maps the NATIVE browser range's anchor nodes
// back into Quill blots. If the native selection's anchor node is no
// longer inside Quill's scroll tree (button click moved focus, Ctrl+A
// selected the whole document, etc.), scroll.find returns null and the
// chain throws "Cannot read properties of null (reading 'offset')". The
// fix is to clear the native selection BEFORE the Quill call so update()
// sees a null range and proceeds with lastRange=null — no normalization,
// no throw, formatText/formatLine apply at the explicit index/length we
// passed and that's all we need.
function clearNativeSelection(): void {
  try { window.getSelection()?.removeAllRanges() } catch { /* SecurityError on cross-origin */ }
}

function applyHeader(level: number | false): void {
  const r = savedRange ?? { index: 0, length: 0 }
  withQuill((q) => {
    const c = clampRange(q, r)
    clearNativeSelection()
    q.formatLine(c.index, c.length, 'header', level === false ? false : level, 'user')
  })
  recomputeFormatState(r)
  openPopover.value = null
}

function toggleInline(key: 'bold' | 'italic' | 'underline' | 'strike'): void {
  const r = savedRange
  if (!r) return
  const wasActive = formatState.value[key] === 'active'
  withQuill((q) => {
    const c = clampRange(q, r)
    clearNativeSelection()
    if (c.length > 0) {
      q.formatText(c.index, c.length, key, !wasActive, 'user')
    } else {
      // Collapsed selection: set internal range then use format() so the
      // pending format applies to the next typed character.
      try {
        const Quill = (window as any).Quill
        q.setSelection(c.index, 0, Quill?.sources?.SILENT ?? 'silent')
        q.format(key, !wasActive, 'user')
      } catch { /* selection race; degrade silently */ }
    }
  })
  recomputeFormatState(r)
}

function toggleList(type: 'bullet' | 'ordered'): void {
  const r = savedRange ?? { index: 0, length: 0 }
  const active = formatState.value.list === type
  withQuill((q) => {
    const c = clampRange(q, r)
    clearNativeSelection()
    q.formatLine(c.index, c.length, 'list', active ? false : type, 'user')
  })
  recomputeFormatState(r)
}

function toggleBlockquote(): void {
  const r = savedRange ?? { index: 0, length: 0 }
  const active = formatState.value.blockquote === 'active'
  withQuill((q) => {
    const c = clampRange(q, r)
    clearNativeSelection()
    q.formatLine(c.index, c.length, 'blockquote', !active, 'user')
  })
  recomputeFormatState(r)
}

function setAlign(value: '' | 'center' | 'right' | 'justify'): void {
  const r = savedRange ?? { index: 0, length: 0 }
  withQuill((q) => {
    const c = clampRange(q, r)
    clearNativeSelection()
    q.formatLine(c.index, c.length, 'align', value === '' ? false : value, 'user')
  })
  recomputeFormatState(r)
}

function clearFormatting(): void {
  const r = savedRange
  if (!r || r.length === 0) return
  withQuill((q) => {
    const c = clampRange(q, r)
    clearNativeSelection()
    q.removeFormat(c.index, c.length, 'user')
  })
  recomputeFormatState(r)
}

// ── §H color/highlight popover ──────────────────────────────────────────

const activeColorTarget = ref<'color' | 'background'>('color')

function openColor(anchor: HTMLElement): void {
  activeColorTarget.value = 'color'
  showPopoverAnchored('color', anchor, 240)
}
function openHighlight(anchor: HTMLElement): void {
  activeColorTarget.value = 'background'
  showPopoverAnchored('highlight', anchor, 240)
}

function onColorPicked(hex: string): void {
  const key = activeColorTarget.value
  const r = savedRange
  if (!r || r.length === 0) {
    openPopover.value = null
    return
  }
  withQuill((q) => {
    const c = clampRange(q, r)
    clearNativeSelection()
    q.formatText(c.index, c.length, key, hex === '' ? false : hex, 'user')
  })
  recomputeFormatState(r)
  openPopover.value = null
}

// ── popover anchoring ───────────────────────────────────────────────────

function togglePopover(kind: Exclude<typeof openPopover.value, null>, anchor: HTMLElement, width = 240): void {
  if (openPopover.value === kind) {
    if (kind === 'link') {
      cancelLinkPopover()
    } else {
      openPopover.value = null
    }
    return
  }
  showPopoverAnchored(kind, anchor, width)
}

function showPopoverAnchored(kind: typeof openPopover.value, anchor: HTMLElement, width = 240): void {
  popoverAnchor.value = anchor
  openPopover.value = kind
  nextTick(() => positionPopover(anchor, width))
}

function onOutsideMousedown(e: MouseEvent): void {
  if (!openPopover.value) return
  const t = e.target as HTMLElement | null
  if (!t) return
  if (barEl.value?.contains(t)) return
  const popoverRoot = document.querySelector('[data-inline-toolbar-popover]') as HTMLElement | null
  if (popoverRoot && popoverRoot.contains(t)) return
  // Spec §G14 / §H7: clicking back into the active editor does NOT close
  // the popover — the editor remains the user's current selection context.
  if (handle.value && handle.value.hostEl.contains(t)) return
  if (openPopover.value === 'link') {
    cancelLinkPopover()
  } else {
    openPopover.value = null
  }
}

function onPopoverKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape') {
    e.preventDefault()
    if (openPopover.value === 'link') cancelLinkPopover()
    else openPopover.value = null
  }
}

// ── mount / unmount ─────────────────────────────────────────────────────

onMounted(() => {
  reducedMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  document.addEventListener('pointerdown', onOutsideMousedown, true)
  measureBar()
  // Mirror each button's accessible name into a title so the icon-only
  // controls also surface a hover tooltip (aria-label already covers SR).
  barEl.value?.querySelectorAll('button[aria-label]').forEach((btn) => {
    if (!btn.getAttribute('title')) {
      btn.setAttribute('title', btn.getAttribute('aria-label') ?? '')
    }
  })
  // Re-measure on canvas resize too
  const main = document.querySelector('.vue-editor__main')
  if (main && typeof ResizeObserver !== 'undefined') {
    const ro = new ResizeObserver(() => measureBar())
    ro.observe(main)
    ;(onMounted as any)._ro = ro
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onOutsideMousedown, true)
  teardownForHandle()
})

// ── group visibility ────────────────────────────────────────────────────

const showAlign = computed(() => collapseStep.value <= 3)
const showColor = computed(() => collapseStep.value <= 2)
const showInsert = computed(() => collapseStep.value <= 1)
const showClear = computed(() => collapseStep.value === 0)
const showOverflow = computed(() => collapseStep.value > 0)

// Text-style label
const textStyleLabel = computed(() => {
  const h = formatState.value.header
  if (h === 'mixed') return 'Mixed'
  if (typeof h === 'number') return `Heading ${h}`
  return 'Paragraph'
})

// Color underbar values
const colorUnderbar = computed(() => {
  const c = formatState.value.color
  if (c === 'mixed') return 'checker'
  if (typeof c === 'string') return c
  return ''
})
const highlightUnderbar = computed(() => {
  const b = formatState.value.background
  if (b === 'mixed') return 'checker'
  if (typeof b === 'string') return b
  return ''
})

const linkIsActive = computed(() => typeof formatState.value.link === 'string' && !!formatState.value.link)

// Disable rule (§D6): while link popover is open, all other buttons are dimmed + inert
const inertBtns = computed(() => openPopover.value === 'link')

// Re-measure when collapseStep changes so subsequent measurements are stable
watch(collapseStep, () => requestPositionUpdate())

// ── style bindings ──────────────────────────────────────────────────────

const barStyle = computed(() => {
  const visible = !!handle.value && fadeShown.value && onScreen.value
  return {
    top: `${top.value}px`,
    left: `${left.value}px`,
    opacity: visible ? 1 : 0,
    pointerEvents: (visible ? 'auto' : 'none') as 'auto' | 'none',
  }
})

// Heroicon trigger inner SVG (inlined to keep the existing picker behaviour)
const heroiconTriggerSvg = HEROICON_TOOLBAR_BUTTON_SVG
</script>

<template>
  <Teleport to="body">
    <div
      ref="barEl"
      data-inline-toolbar
      class="ift"
      :class="{ 'ift--wrapped': wrapped, 'ift--rm': reducedMotion }"
      :style="barStyle"
      :aria-hidden="!handle"
      role="toolbar"
      aria-label="Text formatting"
      @keydown="onToolbarKeydown"
    >
      <!-- Group 1: Text style -->
      <button
        :ref="(el) => registerBtn(el as HTMLElement)"
        type="button"
        class="ift-textstyle"
        data-tb-control="textstyle"
        :tabindex="focusedIdx === 0 ? 0 : -1"
        :disabled="inertBtns"
        :aria-disabled="inertBtns || null"
        :aria-expanded="openPopover === 'text-style'"
        aria-haspopup="menu"
        aria-label="Text style"
        @mousedown.prevent
        @click="(e) => togglePopover('text-style', (e.currentTarget as HTMLElement), 200)"
      >
        <span class="ift-textstyle__label">{{ textStyleLabel }}</span>
        <ChevronDown :size="16" />
      </button>

      <span class="ift-sep" aria-hidden="true" />

      <!-- Group 2: Inline marks -->
      <button
        :ref="(el) => registerBtn(el as HTMLElement)"
        type="button"
        class="ift-btn"
        :class="{ 'ift-btn--active': formatState.bold === 'active', 'ift-btn--mixed': formatState.bold === 'mixed' }"
        :aria-pressed="formatState.bold === 'mixed' ? 'mixed' : (formatState.bold === 'active' ? 'true' : 'false')"
        aria-label="Bold"
        :disabled="inertBtns"
        @mousedown.prevent
        @click="toggleInline('bold')"
      ><Bold :size="18" /></button>
      <button
        :ref="(el) => registerBtn(el as HTMLElement)"
        type="button"
        class="ift-btn"
        :class="{ 'ift-btn--active': formatState.italic === 'active', 'ift-btn--mixed': formatState.italic === 'mixed' }"
        :aria-pressed="formatState.italic === 'mixed' ? 'mixed' : (formatState.italic === 'active' ? 'true' : 'false')"
        aria-label="Italic"
        :disabled="inertBtns"
        @mousedown.prevent
        @click="toggleInline('italic')"
      ><Italic :size="18" /></button>
      <button
        :ref="(el) => registerBtn(el as HTMLElement)"
        type="button"
        class="ift-btn"
        :class="{ 'ift-btn--active': formatState.underline === 'active', 'ift-btn--mixed': formatState.underline === 'mixed' }"
        :aria-pressed="formatState.underline === 'mixed' ? 'mixed' : (formatState.underline === 'active' ? 'true' : 'false')"
        aria-label="Underline"
        :disabled="inertBtns"
        @mousedown.prevent
        @click="toggleInline('underline')"
      ><Underline :size="18" /></button>
      <button
        :ref="(el) => registerBtn(el as HTMLElement)"
        type="button"
        class="ift-btn"
        :class="{ 'ift-btn--active': formatState.strike === 'active', 'ift-btn--mixed': formatState.strike === 'mixed' }"
        :aria-pressed="formatState.strike === 'mixed' ? 'mixed' : (formatState.strike === 'active' ? 'true' : 'false')"
        aria-label="Strikethrough"
        :disabled="inertBtns"
        @mousedown.prevent
        @click="toggleInline('strike')"
      ><Strikethrough :size="18" /></button>

      <span class="ift-sep" aria-hidden="true" />

      <!-- Group 3: Block structure -->
      <button
        :ref="(el) => registerBtn(el as HTMLElement)"
        type="button"
        class="ift-btn"
        :class="{ 'ift-btn--active': formatState.list === 'bullet', 'ift-btn--mixed': formatState.list === 'mixed' }"
        :aria-pressed="formatState.list === 'mixed' ? 'mixed' : (formatState.list === 'bullet' ? 'true' : 'false')"
        aria-label="Bulleted list"
        :disabled="inertBtns"
        @mousedown.prevent
        @click="toggleList('bullet')"
      ><List :size="18" /></button>
      <button
        :ref="(el) => registerBtn(el as HTMLElement)"
        type="button"
        class="ift-btn"
        :class="{ 'ift-btn--active': formatState.list === 'ordered', 'ift-btn--mixed': formatState.list === 'mixed' }"
        :aria-pressed="formatState.list === 'mixed' ? 'mixed' : (formatState.list === 'ordered' ? 'true' : 'false')"
        aria-label="Numbered list"
        :disabled="inertBtns"
        @mousedown.prevent
        @click="toggleList('ordered')"
      ><ListOrdered :size="18" /></button>
      <button
        :ref="(el) => registerBtn(el as HTMLElement)"
        type="button"
        class="ift-btn"
        :class="{ 'ift-btn--active': formatState.blockquote === 'active', 'ift-btn--mixed': formatState.blockquote === 'mixed' }"
        :aria-pressed="formatState.blockquote === 'mixed' ? 'mixed' : (formatState.blockquote === 'active' ? 'true' : 'false')"
        aria-label="Blockquote"
        :disabled="inertBtns"
        @mousedown.prevent
        @click="toggleBlockquote"
      ><Quote :size="18" /></button>

      <span class="ift-sep" aria-hidden="true" />

      <!-- Group 4: Link -->
      <button
        :ref="(el) => registerBtn(el as HTMLElement)"
        type="button"
        class="ift-btn"
        :class="{ 'ift-btn--active': linkIsActive }"
        :aria-pressed="linkIsActive ? 'true' : null"
        :aria-label="linkIsActive ? 'Edit link' : 'Insert link'"
        data-tb-control="link"
        @mousedown.prevent
        @click="(e) => (openPopover === 'link' ? cancelLinkPopover() : openLinkPopover(e.currentTarget as HTMLElement))"
      ><LinkIcon :size="18" /></button>

      <!-- Group 5: Alignment -->
      <template v-if="showAlign">
        <span class="ift-sep" aria-hidden="true" />
        <button
          :ref="(el) => registerBtn(el as HTMLElement)"
          type="button"
          class="ift-btn"
          :class="{ 'ift-btn--active': formatState.align === '' || formatState.align === null }"
          :aria-pressed="(formatState.align === '' || formatState.align === null) ? 'true' : 'false'"
          aria-label="Align left"
          :disabled="inertBtns"
          @mousedown.prevent
          @click="setAlign('')"
        ><AlignLeft :size="18" /></button>
        <button
          :ref="(el) => registerBtn(el as HTMLElement)"
          type="button"
          class="ift-btn"
          :class="{ 'ift-btn--active': formatState.align === 'center' }"
          :aria-pressed="formatState.align === 'center' ? 'true' : 'false'"
          aria-label="Align center"
          :disabled="inertBtns"
          @mousedown.prevent
          @click="setAlign('center')"
        ><AlignCenter :size="18" /></button>
        <button
          :ref="(el) => registerBtn(el as HTMLElement)"
          type="button"
          class="ift-btn"
          :class="{ 'ift-btn--active': formatState.align === 'right' }"
          :aria-pressed="formatState.align === 'right' ? 'true' : 'false'"
          aria-label="Align right"
          :disabled="inertBtns"
          @mousedown.prevent
          @click="setAlign('right')"
        ><AlignRight :size="18" /></button>
        <button
          :ref="(el) => registerBtn(el as HTMLElement)"
          type="button"
          class="ift-btn"
          :class="{ 'ift-btn--active': formatState.align === 'justify' }"
          :aria-pressed="formatState.align === 'justify' ? 'true' : 'false'"
          aria-label="Justify"
          :disabled="inertBtns"
          @mousedown.prevent
          @click="setAlign('justify')"
        ><AlignJustify :size="18" /></button>
      </template>

      <!-- Group 6: Color & highlight -->
      <template v-if="showColor">
        <span class="ift-sep" aria-hidden="true" />
        <button
          :ref="(el) => registerBtn(el as HTMLElement)"
          type="button"
          class="ift-btn ift-btn--with-underbar"
          aria-label="Text color"
          :disabled="inertBtns"
          @mousedown.prevent
          @click="(e) => (openPopover === 'color' ? (openPopover = null) : openColor(e.currentTarget as HTMLElement))"
        >
          <Palette :size="18" />
          <span class="ift-underbar" :class="{ 'ift-underbar--checker': colorUnderbar === 'checker' }" :style="colorUnderbar && colorUnderbar !== 'checker' ? { backgroundColor: colorUnderbar } : undefined" />
        </button>
        <button
          :ref="(el) => registerBtn(el as HTMLElement)"
          type="button"
          class="ift-btn ift-btn--with-underbar"
          aria-label="Highlight"
          :disabled="inertBtns"
          @mousedown.prevent
          @click="(e) => (openPopover === 'highlight' ? (openPopover = null) : openHighlight(e.currentTarget as HTMLElement))"
        >
          <Highlighter :size="18" />
          <span class="ift-underbar" :class="{ 'ift-underbar--checker': highlightUnderbar === 'checker' }" :style="highlightUnderbar && highlightUnderbar !== 'checker' ? { backgroundColor: highlightUnderbar } : undefined" />
        </button>
      </template>

      <!-- Group 7: Insertions -->
      <template v-if="showInsert">
        <span class="ift-sep" aria-hidden="true" />
        <button
          :ref="(el) => registerBtn(el as HTMLElement)"
          type="button"
          class="ift-btn"
          aria-label="Insert image"
          :disabled="imageUploading || inertBtns"
          @mousedown.prevent
          @click="openImage"
        >
          <ImageIcon v-if="!imageUploading" :size="18" />
          <span v-else class="ift-spinner" aria-hidden="true" />
        </button>
        <button
          :ref="(el) => registerBtn(el as HTMLElement)"
          type="button"
          class="ift-btn ift-btn--heroicon"
          aria-label="Insert icon"
          :disabled="inertBtns"
          @mousedown.prevent
          @click="(e) => openHeroicon(e.currentTarget as HTMLElement)"
          v-html="heroiconTriggerSvg"
        />
      </template>

      <!-- Group 8: Clear formatting -->
      <template v-if="showClear">
        <span class="ift-sep" aria-hidden="true" />
        <button
          :ref="(el) => registerBtn(el as HTMLElement)"
          type="button"
          class="ift-btn"
          aria-label="Clear formatting"
          :disabled="formatState.collapsed || inertBtns"
          @mousedown.prevent
          @click="clearFormatting"
        ><RemoveFormatting :size="18" /></button>
      </template>

      <!-- Overflow trigger -->
      <template v-if="showOverflow">
        <span class="ift-sep" aria-hidden="true" />
        <button
          :ref="(el) => registerBtn(el as HTMLElement)"
          type="button"
          class="ift-btn"
          aria-label="More formatting options"
          :aria-expanded="openPopover === 'overflow'"
          :disabled="inertBtns"
          @mousedown.prevent
          @click="(e) => togglePopover('overflow', (e.currentTarget as HTMLElement), 240)"
        ><MoreHorizontal :size="18" /></button>
      </template>

      <div v-if="errorToast" class="ift-toast">{{ errorToast }}</div>
    </div>

    <!-- Anchored popovers — presentational sub-components; the orchestrator
         supplies the shared frame (position, data-attr, keydown) as
         fall-through attributes and owns dispatch via @apply/@pick. -->
    <InlineTextStyleMenu
      v-if="handle && openPopover === 'text-style'"
      data-inline-toolbar-popover
      :style="{ top: popoverTop + 'px', left: popoverLeft + 'px' }"
      :header="formatState.header"
      :body-family="store.themeBodyFamily"
      :heading-family="store.themeHeadingFamily"
      @keydown="onPopoverKeydown"
      @apply="applyHeader"
    />

    <InlineColorPopover
      v-if="handle && (openPopover === 'color' || openPopover === 'highlight')"
      data-inline-toolbar-popover
      :style="{ top: popoverTop + 'px', left: popoverLeft + 'px', width: '240px' }"
      @keydown="onPopoverKeydown"
      @pick="onColorPicked"
    />

    <InlineLinkPopover
      v-if="handle && openPopover === 'link'"
      :ctl="linkCtl"
      data-inline-toolbar-popover
      :style="{ top: popoverTop + 'px', left: popoverLeft + 'px', width: '320px' }"
      @keydown="onPopoverKeydown"
    />

    <!-- ── Overflow menu ─────────────────────────────────────────────── -->
    <div
      v-if="handle && openPopover === 'overflow'"
      data-inline-toolbar-popover
      class="ift-popover ift-overflow-menu"
      :style="{ top: popoverTop + 'px', left: popoverLeft + 'px', width: '240px' }"
      @keydown="onPopoverKeydown"
    >
      <template v-if="collapseStep >= 4">
        <p class="ift-overflow-menu__heading">Alignment</p>
        <button class="ift-overflow-menu__row" @mousedown.prevent @click="setAlign('')"><AlignLeft :size="16" /><span>Align left</span></button>
        <button class="ift-overflow-menu__row" @mousedown.prevent @click="setAlign('center')"><AlignCenter :size="16" /><span>Align center</span></button>
        <button class="ift-overflow-menu__row" @mousedown.prevent @click="setAlign('right')"><AlignRight :size="16" /><span>Align right</span></button>
        <button class="ift-overflow-menu__row" @mousedown.prevent @click="setAlign('justify')"><AlignJustify :size="16" /><span>Justify</span></button>
      </template>
      <template v-if="collapseStep >= 3">
        <p class="ift-overflow-menu__heading">Color &amp; highlight</p>
        <button class="ift-overflow-menu__row" @mousedown.prevent @click="(e) => openColor(e.currentTarget as HTMLElement)"><Palette :size="16" /><span>Text color</span></button>
        <button class="ift-overflow-menu__row" @mousedown.prevent @click="(e) => openHighlight(e.currentTarget as HTMLElement)"><Highlighter :size="16" /><span>Highlight</span></button>
      </template>
      <template v-if="collapseStep >= 2">
        <p class="ift-overflow-menu__heading">Insertions</p>
        <button class="ift-overflow-menu__row" :disabled="imageUploading" @mousedown.prevent @click="openImage"><ImageIcon :size="16" /><span>Insert image</span></button>
        <button class="ift-overflow-menu__row" @mousedown.prevent @click="(e) => openHeroicon(e.currentTarget as HTMLElement)"><span v-html="heroiconTriggerSvg" class="ift-overflow-menu__heroicon" /><span>Insert icon</span></button>
      </template>
      <template v-if="collapseStep >= 1">
        <p class="ift-overflow-menu__heading">Clear formatting</p>
        <button class="ift-overflow-menu__row" :disabled="formatState.collapsed" @mousedown.prevent @click="clearFormatting"><RemoveFormatting :size="16" /><span>Clear formatting</span></button>
      </template>
    </div>
  </Teleport>
</template>

<style>
/* docs/inline-formatting-toolbar-spec.md §4.2/§4.3. The toolbar is always
   dark (§4.1 / §M10) and lives in the body via <Teleport>; it never
   participates in any preview HTML v-html swap (§C15).  Styles are global
   (no scoped) so popovers teleported to <body> can match the bar. */

.ift {
  position: fixed;
  z-index: 60;
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0;
  max-width: 480px;
  padding: 4px;
  background: #1f2937;
  border: 1px solid #374151;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
  font: 13px/1 'Inter', system-ui, sans-serif;
  color: #d1d5db;
  transition: opacity 0.08s ease-out;
  row-gap: 2px;
}

.ift--wrapped {
  /* legacy modifier kept as no-op; default layout now wraps */
}

.ift--rm,
.ift--rm * { transition: none !important; animation: none !important; }

.ift-sep {
  display: inline-block;
  width: 1px;
  height: 18px;
  margin: 0 6px;
  background: #374151;
}

.ift-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  background: transparent;
  border: 0;
  border-radius: 6px;
  color: #d1d5db;
  cursor: pointer;
  transition: background 0.08s ease;
}
.ift-btn:hover:not(:disabled) { background: #374151; }
.ift-btn:active:not(:disabled) { background: #111827; }
.ift-btn:focus-visible {
  outline: 2px solid #818cf8;
  outline-offset: 2px;
}
.ift-btn:disabled { cursor: default; opacity: 0.4; }
.ift-btn[aria-disabled="true"] { cursor: default; opacity: 0.4; pointer-events: none; }
.ift-btn svg { stroke-width: 2; }

.ift-btn--active {
  background: #4f46e5;
  color: #fff;
}
.ift-btn--active:hover:not(:disabled) { background: #4f46e5; }
.ift-btn--active:active:not(:disabled) { background: #4338ca; }

.ift-btn--mixed {
  background: rgba(79, 70, 229, 0.35);
  color: #c7d2fe;
}

.ift-btn--with-underbar {
  flex-direction: column;
  padding-top: 2px;
}
.ift-underbar {
  display: block;
  width: 18px;
  height: 3px;
  margin-top: -1px;
  border-radius: 1px;
  background: transparent;
}
.ift-underbar--checker {
  background: repeating-linear-gradient(
    45deg,
    #6b7280,
    #6b7280 2px,
    #d1d5db 2px,
    #d1d5db 4px
  );
}

.ift-btn--heroicon :deep(svg),
.ift-btn--heroicon > svg {
  width: 18px;
  height: 18px;
  color: #d1d5db;
}

.ift-textstyle {
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  width: 120px;
  height: 32px;
  padding: 0 8px;
  background: transparent;
  border: 0;
  border-radius: 6px;
  color: #e5e7eb;
  font: 13px/1 'Inter', system-ui, sans-serif;
  cursor: pointer;
}
.ift-textstyle:hover:not(:disabled) { background: #374151; }
.ift-textstyle:focus-visible { outline: 2px solid #818cf8; outline-offset: 2px; }
.ift-textstyle:disabled { cursor: default; opacity: 0.4; }
.ift-textstyle__label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.ift-spinner {
  display: inline-block;
  width: 12px;
  height: 12px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: ift-spin 0.8s linear infinite;
}
@keyframes ift-spin { to { transform: rotate(360deg); } }

.ift-toast {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  padding: 6px 10px;
  background: #dc2626;
  color: #fff;
  font-size: 13px;
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
  white-space: nowrap;
}

/* ── Popovers ────────────────────────────────────────────────────────── */

.ift-popover {
  position: fixed;
  z-index: 61;
  padding: 8px;
  background: #111827;
  border: 1px solid #374151;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
  color: #e5e7eb;
  font: 13px/1.3 'Inter', system-ui, sans-serif;
}

/* Text-style, colour, and link popover styles live with their sub-components
   under components/inline-toolbar/ (global, like the rest of the toolbar). The
   shared .ift-popover frame above still applies to them via the class on each
   sub-component's root. */

.ift-overflow-menu { padding: 4px; }
.ift-overflow-menu__heading {
  margin: 6px 12px 2px;
  font-size: 10px;
  letter-spacing: 0.05em;
  color: #9ca3af;
  text-transform: uppercase;
}
.ift-overflow-menu__row {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  min-height: 36px;
  padding: 0 12px;
  background: transparent;
  border: 0;
  color: #e5e7eb;
  font: 12px/1 'Inter', system-ui, sans-serif;
  text-align: left;
  cursor: pointer;
}
.ift-overflow-menu__row:hover { background: #374151; }
.ift-overflow-menu__row:disabled { opacity: 0.4; cursor: default; }
.ift-overflow-menu__heroicon { width: 16px; height: 16px; display: inline-flex; }
.ift-overflow-menu__heroicon svg { width: 16px; height: 16px; color: #d1d5db; }
</style>
