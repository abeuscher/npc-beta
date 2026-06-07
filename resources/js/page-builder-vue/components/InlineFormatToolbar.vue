<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  AlignCenter,
  AlignJustify,
  AlignLeft,
  AlignRight,
  Bold,
  Check,
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
import ColorPicker from './primitives/ColorPicker.vue'
import { useEditorStore } from '../stores/editor'
import { useInlineToolbarPosition } from '../composables/useInlineToolbarPosition'
import { HEROICON_TOOLBAR_BUTTON_SVG } from '../../admin/heroicon-blot.js'
import { openHeroiconPicker, setHeroiconsUrl } from '../../admin/heroicon-picker.js'

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

const errorToast = ref('')
let errorToastTimer: ReturnType<typeof setTimeout> | null = null
const imageUploading = ref(false)

// Roving tabindex within the toolbar buttons.
const focusedIdx = ref(0)
const buttonRefs = ref<HTMLElement[]>([])

// Link popover state.
interface LinkState {
  mode: 'insert' | 'edit'
  url: string
  pageSlug: string
  linkText: string
  openInNewTab: boolean
  savedRange: { index: number; length: number } | null
  originalText: string
}
const linkState = ref<LinkState>({
  mode: 'insert', url: '', pageSlug: '', linkText: '',
  openInNewTab: false, savedRange: null, originalText: '',
})
const linkUrlInput = ref<HTMLInputElement | null>(null)
const pagePickerOpen = ref(false)
const pageQuery = ref('')
const pageHighlight = ref(0)

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

// Restore the last known good range on the editor before applying a
// format. Without this, the native browser selection may have drifted
// (popover opened, button focused) and q.format()/formatText would
// silently no-op. We use Quill's SILENT source so this doesn't fire
// editor-change re-entry.
function restoreRange(q: any): { index: number; length: number } | null {
  if (!savedRange) return null
  try {
    const len = q.getLength()
    const idx = Math.max(0, Math.min(savedRange.index, len - 1))
    const lenClamped = Math.max(0, Math.min(savedRange.length, len - 1 - idx))
    const Quill = (window as any).Quill
    q.setSelection(idx, lenClamped, Quill?.sources?.SILENT ?? 'silent')
    return { index: idx, length: lenClamped }
  } catch {
    return null
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

// ── §G link popover ─────────────────────────────────────────────────────

function findLinkBounds(q: any, index: number, url: string): { index: number; length: number } {
  try {
    let start = index
    while (start > 0) {
      const f = q.getFormat(start - 1, 1)
      if (f.link !== url) break
      start--
    }
    let end = index
    const total = q.getLength()
    while (end < total) {
      const f = q.getFormat(end, 1)
      if (f.link !== url) break
      end++
    }
    return { index: start, length: end - start }
  } catch {
    return { index, length: 0 }
  }
}

function findAnchorAt(q: any, index: number): HTMLAnchorElement | null {
  try {
    const result = q.getLeaf(index)
    const leaf = Array.isArray(result) ? result[0] : null
    let node: any = leaf?.domNode ?? null
    while (node && node.tagName !== 'A') node = node.parentElement
    return node as HTMLAnchorElement | null
  } catch {
    return null
  }
}

function openLinkPopover(anchor: HTMLElement): void {
  const h = handle.value
  if (!h) return
  const q = h.quill
  let range: { index: number; length: number } | null = null
  let fmt: Record<string, any> = {}
  let text = ''
  try {
    range = q.getSelection() ?? { index: q.getLength(), length: 0 }
    fmt = q.getFormat(range.index, range.length)
    text = range.length > 0 ? q.getText(range.index, range.length) : ''
  } catch {
    range = { index: 0, length: 0 }
    fmt = {}
    text = ''
  }
  const existing = typeof fmt.link === 'string' ? fmt.link : ''

  // If the caret sits inside an existing link, walk the document to find
  // the link's bounds so Save/Remove acts on the whole link.
  let saved = { index: range!.index, length: range!.length }
  if (existing && range!.length === 0) {
    saved = findLinkBounds(q, range!.index, existing)
  }
  const anchorEl = existing ? findAnchorAt(q, saved.index) : null
  const blank = anchorEl?.getAttribute('target') === '_blank'

  let savedText = ''
  if (existing) {
    try { savedText = q.getText(saved.index, saved.length) } catch { savedText = text }
  } else {
    savedText = text
  }

  linkState.value = {
    mode: existing ? 'edit' : 'insert',
    url: existing,
    pageSlug: '',
    linkText: savedText,
    openInNewTab: !!blank,
    savedRange: saved,
    originalText: savedText,
  }
  pageQuery.value = ''
  pagePickerOpen.value = false
  pageHighlight.value = 0
  showPopoverAnchored('link', anchor)
  nextTick(() => {
    linkUrlInput.value?.focus()
    linkUrlInput.value?.select()
  })
}

function saveLink(): void {
  const ls = linkState.value
  if (!ls.savedRange) return
  const url = ls.url.trim()
  if (!url) return
  withQuill((q) => {
    const Quill = (window as any).Quill
    const SOURCE = Quill?.sources?.USER ?? 'user'

    const target = ls.openInNewTab ? '_blank' : null
    const rel = ls.openInNewTab ? 'noopener noreferrer' : null

    if (ls.linkText && ls.linkText !== ls.originalText && ls.savedRange!.length > 0) {
      q.deleteText(ls.savedRange!.index, ls.savedRange!.length, SOURCE)
      q.insertText(ls.savedRange!.index, ls.linkText, { link: url }, SOURCE)
      if (target) {
        q.formatText(ls.savedRange!.index, ls.linkText.length, 'link', url, SOURCE)
      }
      applyLinkAttrs(ls.savedRange!.index, ls.linkText.length, target, rel)
      q.setSelection(ls.savedRange!.index + ls.linkText.length, 0, SOURCE)
    } else if (ls.savedRange!.length === 0) {
      q.insertText(ls.savedRange!.index, ls.linkText || url, { link: url }, SOURCE)
      const insertedLen = (ls.linkText || url).length
      applyLinkAttrs(ls.savedRange!.index, insertedLen, target, rel)
      q.setSelection(ls.savedRange!.index + insertedLen, 0, SOURCE)
    } else {
      q.formatText(ls.savedRange!.index, ls.savedRange!.length, 'link', url, SOURCE)
      applyLinkAttrs(ls.savedRange!.index, ls.savedRange!.length, target, rel)
      q.setSelection(ls.savedRange!.index + ls.savedRange!.length, 0, SOURCE)
    }
  })
  closeLinkPopover()
  recomputeFormatState()
}

function applyLinkAttrs(index: number, length: number, target: string | null, rel: string | null): void {
  // Quill's link format doesn't natively persist target/rel through its
  // Delta. Reach into the DOM for the anchor wrapping the saved range and
  // set the attributes directly — the inline-edit raw-write contract
  // serialises root.innerHTML, so the resulting DOM (and these attrs) is
  // what we save. Sanitizer (305 §6.2) keeps target/rel on persistence.
  withQuill((q) => {
    const seen = new Set<HTMLAnchorElement>()
    const total = q.getLength()
    const probeAt = Math.max(0, Math.min(index, total - 1))
    const a1 = findAnchorAt(q, probeAt)
    if (a1) seen.add(a1)
    if (length > 0) {
      const a2 = findAnchorAt(q, Math.max(0, Math.min(index + length - 1, total - 1)))
      if (a2) seen.add(a2)
    }
    seen.forEach((a) => {
      if (target) a.setAttribute('target', target); else a.removeAttribute('target')
      if (rel) a.setAttribute('rel', rel); else a.removeAttribute('rel')
    })
  })
}

function removeLink(): void {
  const ls = linkState.value
  if (!ls.savedRange) return
  withQuill((q) => {
    const Quill = (window as any).Quill
    const SOURCE = Quill?.sources?.USER ?? 'user'
    q.formatText(ls.savedRange!.index, ls.savedRange!.length, 'link', false, SOURCE)
  })
  applyLinkAttrs(ls.savedRange.index, ls.savedRange.length, null, null)
  closeLinkPopover()
  recomputeFormatState()
}

function cancelLinkPopover(): void {
  closeLinkPopover()
}

function closeLinkPopover(): void {
  const ls = linkState.value
  openPopover.value = null
  pagePickerOpen.value = false
  if (ls.savedRange) {
    withQuill((q) => {
      const Quill = (window as any).Quill
      const SOURCE = Quill?.sources?.SILENT ?? 'silent'
      q.setSelection(ls.savedRange!.index, ls.savedRange!.length, SOURCE)
      q.focus()
    })
  }
}

const filteredPages = computed(() => {
  const q = pageQuery.value.trim().toLowerCase()
  const all = store.pages
  if (!q) return all
  return all.filter((p) => (p.title || '').toLowerCase().includes(q) || (p.url || '').toLowerCase().includes(q))
})

function pickPage(slug: string, url: string): void {
  linkState.value.pageSlug = slug
  linkState.value.url = url
  pagePickerOpen.value = false
  pageQuery.value = ''
}

function onUrlInput(): void {
  // §G7: editing URL clears any picked page
  if (linkState.value.pageSlug) {
    linkState.value.pageSlug = ''
  }
}

// ── §H color/highlight popover ──────────────────────────────────────────

const activeColorTarget = ref<'color' | 'background'>('color')
const colorPickerRef = ref<any>(null)

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

// ── §F7 image + heroicon ────────────────────────────────────────────────

let imageInput: HTMLInputElement | null = null

function openImage(): void {
  const h = handle.value
  if (!h) return
  if (!imageInput) {
    imageInput = document.createElement('input')
    imageInput.type = 'file'
    imageInput.accept = 'image/png,image/jpeg,image/gif,image/webp,image/svg+xml'
    imageInput.style.display = 'none'
    document.body.appendChild(imageInput)
    imageInput.addEventListener('change', onImageChosen)
  }
  imageInput.value = ''
  imageInput.click()
}

async function onImageChosen(): Promise<void> {
  const file = imageInput?.files?.[0]
  if (!file) return
  const h = handle.value
  if (!h) return
  const q = h.quill
  let range: any = null
  try { range = q.getSelection() } catch { /* selection lost */ }
  if (!range) {
    try { range = { index: q.getLength() - 1, length: 0 } } catch { range = { index: 0, length: 0 } }
  }
  const uploadUrl = store.inlineImageUploadUrl
  if (!uploadUrl) return
  imageUploading.value = true
  try {
    const form = new FormData()
    form.append('file', file)
    form.append('model_type', 'page_widget')
    form.append('model_id', h.widgetId)
    const csrfMeta = document.head.querySelector('meta[name=csrf-token]') as HTMLMetaElement | null
    const res = await fetch(uploadUrl, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfMeta?.content ?? '' },
      body: form,
    })
    const data = await res.json()
    if (data.url) {
      q.insertEmbed(range.index, 'image', data.url, 'user')
      q.setSelection(range.index + 1, 0, 'user')
    } else {
      showError('Image upload failed.')
    }
  } catch (e) {
    console.error('Inline image upload failed', e)
    showError('Image upload failed.')
  } finally {
    imageUploading.value = false
  }
}

function openHeroicon(anchor: HTMLElement): void {
  const h = handle.value
  if (!h) return
  if (store.heroiconsUrl) setHeroiconsUrl(store.heroiconsUrl)
  const q = h.quill
  let range: any = null
  try { range = q.getSelection() } catch { /* selection lost */ }
  if (!range) {
    try { range = { index: q.getLength() - 1, length: 0 } } catch { range = { index: 0, length: 0 } }
  }
  openHeroiconPicker(anchor, (icon: { name: string; svg: string }) => {
    withQuill((qq) => {
      const Quill = (window as any).Quill
      qq.insertEmbed(range.index, 'heroicon', icon, Quill?.sources?.USER ?? 'user')
      qq.setSelection(range.index + 1, 0, Quill?.sources?.SILENT ?? 'silent')
    })
  })
}

function showError(msg: string): void {
  errorToast.value = msg
  if (errorToastTimer) clearTimeout(errorToastTimer)
  errorToastTimer = setTimeout(() => {
    errorToast.value = ''
    errorToastTimer = null
  }, 4000)
}

// ── §K keyboard / accessibility ─────────────────────────────────────────

function registerBtn(el: HTMLElement | null): void {
  if (el && !buttonRefs.value.includes(el)) buttonRefs.value.push(el)
}

function enabledButtons(): HTMLElement[] {
  return buttonRefs.value.filter((b) => !b.hasAttribute('disabled') && b.offsetParent !== null)
}

function onToolbarKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape') {
    e.preventDefault()
    const h = handle.value
    if (h) {
      h.quill.focus()
    }
    return
  }
  if (e.key === 'ArrowRight' || e.key === 'ArrowLeft' || e.key === 'Home' || e.key === 'End') {
    e.preventDefault()
    const list = enabledButtons()
    if (list.length === 0) return
    const cur = list.indexOf(document.activeElement as HTMLElement)
    let next = cur
    if (e.key === 'ArrowRight') next = Math.min(list.length - 1, cur + 1)
    else if (e.key === 'ArrowLeft') next = Math.max(0, cur - 1)
    else if (e.key === 'Home') next = 0
    else next = list.length - 1
    list[next]?.focus()
    focusedIdx.value = next
  }
}

function onEditorKeydown(e: KeyboardEvent): void {
  // §K9: Alt+F10 enters the toolbar
  if (e.altKey && e.key === 'F10') {
    e.preventDefault()
    const list = enabledButtons()
    list[0]?.focus()
    focusedIdx.value = 0
    return
  }
  // §F4.2: Cmd/Ctrl+K opens link popover
  if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
    e.preventDefault()
    const linkBtn = buttonRefs.value.find((b) => b.dataset.tbControl === 'link')
    if (linkBtn) openLinkPopover(linkBtn)
  }
}

// Bind Alt+F10 + Cmd+K once on mount (live for as long as the page builder
// is on screen). The handler is a no-op when no handle is active.
function onWindowKeydown(e: KeyboardEvent): void {
  if (!handle.value) return
  const target = e.target as HTMLElement | null
  // Only react when the keystroke comes from inside the active Quill editor
  // (the active host element).
  if (target && handle.value.hostEl.contains(target)) {
    onEditorKeydown(e)
  }
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
  window.addEventListener('keydown', onWindowKeydown, true)
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
  window.removeEventListener('keydown', onWindowKeydown, true)
  teardownForHandle()
  if (errorToastTimer) clearTimeout(errorToastTimer)
  if (imageInput && imageInput.parentNode) imageInput.parentNode.removeChild(imageInput)
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

    <!-- ── Text-style menu popover ───────────────────────────────────── -->
    <div
      v-if="handle && openPopover === 'text-style'"
      data-inline-toolbar-popover
      class="ift-popover ift-textstyle-menu"
      role="menu"
      :style="{ top: popoverTop + 'px', left: popoverLeft + 'px' }"
      @keydown="onPopoverKeydown"
    >
      <button
        type="button"
        class="ift-textstyle-menu__row"
        role="menuitem"
        :style="{ fontFamily: store.themeBodyFamily }"
        @mousedown.prevent
        @click="applyHeader(false)"
      >
        <span>Paragraph</span>
        <Check v-if="formatState.header === null" :size="14" class="ift-textstyle-menu__check" />
      </button>
      <button
        v-for="n in [1, 2, 3, 4, 5, 6]"
        :key="n"
        type="button"
        class="ift-textstyle-menu__row"
        role="menuitem"
        :style="{ fontFamily: store.themeHeadingFamily, fontWeight: 700, fontSize: Math.min(22, 14 + (7 - n) * 1.5) + 'px' }"
        @mousedown.prevent
        @click="applyHeader(n)"
      >
        <span>Heading {{ n }}</span>
        <Check v-if="formatState.header === n" :size="14" class="ift-textstyle-menu__check" />
      </button>
    </div>

    <!-- ── Color popover ─────────────────────────────────────────────── -->
    <div
      v-if="handle && (openPopover === 'color' || openPopover === 'highlight')"
      data-inline-toolbar-popover
      class="ift-popover ift-color-popover"
      :style="{ top: popoverTop + 'px', left: popoverLeft + 'px', width: '240px' }"
      @keydown="onPopoverKeydown"
    >
      <ColorPicker
        ref="colorPickerRef"
        :model-value="''"
        panel-only
        @update:model-value="onColorPicked"
      />
    </div>

    <!-- ── Link popover ──────────────────────────────────────────────── -->
    <div
      v-if="handle && openPopover === 'link'"
      data-inline-toolbar-popover
      class="ift-popover ift-link-popover"
      :style="{ top: popoverTop + 'px', left: popoverLeft + 'px', width: '320px' }"
      @keydown="onPopoverKeydown"
    >
      <label class="ift-link-label">URL</label>
      <input
        ref="linkUrlInput"
        v-model="linkState.url"
        type="text"
        class="ift-link-input"
        placeholder="https://example.com"
        @input="onUrlInput"
        @keydown.enter.prevent="saveLink"
      />

      <label class="ift-link-label">Or pick a page</label>
      <div class="ift-link-picker">
        <input
          v-model="pageQuery"
          type="text"
          class="ift-link-input"
          placeholder="Search site pages…"
          @focus="pagePickerOpen = true"
          @input="pagePickerOpen = true"
          @keydown.down.prevent="pageHighlight = Math.min(filteredPages.length - 1, pageHighlight + 1)"
          @keydown.up.prevent="pageHighlight = Math.max(0, pageHighlight - 1)"
          @keydown.enter.prevent="(() => { const p = filteredPages[pageHighlight]; if (p) pickPage(p.slug, p.url || '') })()"
        />
        <ul v-if="pagePickerOpen && filteredPages.length" class="ift-link-picker__list">
          <li
            v-for="(p, i) in filteredPages.slice(0, 12)"
            :key="p.slug"
            class="ift-link-picker__row"
            :class="{ 'ift-link-picker__row--active': i === pageHighlight }"
            @mousedown.prevent
            @click="pickPage(p.slug, p.url || '')"
          >
            <span class="ift-link-picker__title">{{ p.title }}</span>
            <span class="ift-link-picker__url">{{ p.url }}</span>
          </li>
        </ul>
      </div>

      <label class="ift-link-label">Link text</label>
      <input
        v-model="linkState.linkText"
        type="text"
        class="ift-link-input"
        @keydown.enter.prevent="saveLink"
      />

      <label class="ift-link-check">
        <input v-model="linkState.openInNewTab" type="checkbox" />
        <span>Open in new tab</span>
      </label>

      <div class="ift-link-actions">
        <button
          v-if="linkState.mode === 'edit'"
          type="button"
          class="ift-link-btn ift-link-btn--remove"
          @mousedown.prevent
          @click="removeLink"
        >Remove</button>
        <div class="ift-link-actions__right">
          <button
            type="button"
            class="ift-link-btn"
            @mousedown.prevent
            @click="cancelLinkPopover"
          >Cancel</button>
          <button
            type="button"
            class="ift-link-btn ift-link-btn--primary"
            :disabled="!linkState.url.trim()"
            @mousedown.prevent
            @click="saveLink"
          >Save</button>
        </div>
      </div>
    </div>

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

.ift-textstyle-menu { padding: 4px; min-width: 120px; max-width: 280px; }
.ift-textstyle-menu__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  min-height: 28px;
  padding: 4px 12px;
  background: transparent;
  border: 0;
  border-radius: 4px;
  color: #e5e7eb;
  text-align: left;
  cursor: pointer;
}
.ift-textstyle-menu__row:hover { background: #374151; }
.ift-textstyle-menu__check { color: #818cf8; }

.ift-color-popover { padding: 8px; }
/* ColorPicker primitive paints itself; toolbar provides the dark frame. */
.ift-color-popover .color-picker__popover { background: transparent; border: 0; padding: 0; box-shadow: none; }

.ift-link-popover { padding: 12px; }
.ift-link-label {
  display: block;
  margin: 4px 0 4px;
  font-size: 11px;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.ift-link-input {
  width: 100%;
  height: 32px;
  padding: 0 8px;
  background: #1f2937;
  border: 1px solid #374151;
  border-radius: 6px;
  color: #e5e7eb;
  font: 13px/1 'Inter', system-ui, sans-serif;
  box-sizing: border-box;
}
.ift-link-input:focus { border-color: #818cf8; outline: none; }

.ift-link-picker { position: relative; }
.ift-link-picker__list {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  max-height: 180px;
  margin: 4px 0 0;
  padding: 4px 0;
  list-style: none;
  background: #1f2937;
  border: 1px solid #374151;
  border-radius: 6px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
  overflow: auto;
  z-index: 2;
}
.ift-link-picker__row {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 6px 10px;
  cursor: pointer;
}
.ift-link-picker__row:hover,
.ift-link-picker__row--active { background: #374151; }
.ift-link-picker__title { color: #e5e7eb; font-size: 13px; }
.ift-link-picker__url { color: #9ca3af; font-size: 11px; }

.ift-link-check {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 10px 0 0;
  color: #e5e7eb;
  font-size: 13px;
}
.ift-link-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 12px;
  gap: 8px;
}
.ift-link-actions__right { display: flex; gap: 8px; margin-left: auto; }
.ift-link-btn {
  height: 30px;
  padding: 0 12px;
  background: #1f2937;
  border: 1px solid #374151;
  border-radius: 6px;
  color: #e5e7eb;
  font: 13px/1 'Inter', system-ui, sans-serif;
  cursor: pointer;
}
.ift-link-btn:hover { background: #374151; }
.ift-link-btn:focus-visible { outline: 2px solid #818cf8; outline-offset: 2px; }
.ift-link-btn:disabled { opacity: 0.4; cursor: default; }
.ift-link-btn--primary { background: #4f46e5; border-color: #4f46e5; color: #fff; }
.ift-link-btn--primary:hover { background: #4338ca; }
.ift-link-btn--remove { background: transparent; border-color: transparent; color: #f87171; }
.ift-link-btn--remove:hover { background: rgba(220, 38, 38, 0.18); }

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
