import { computed, nextTick, ref, type ComputedRef, type Ref } from 'vue'
import type { PageRef } from '../types'

// Session 347 — link-popover seam extracted from InlineFormatToolbar.vue
// (session 306, §G of docs/inline-formatting-toolbar-spec.md). Owns the
// insert/edit link flow end-to-end: popover state + the site-page picker, the
// link-bounds walk, the Quill mutations (insert/format/remove + the DOM
// target/rel write Quill's Delta can't carry), and the focus restore on close.
// The orchestrator owns the Quill helpers (withQuill), the open-popover state,
// the anchored-popover positioning, and format-state recompute; this composable
// receives them as deps. Behaviour is preserved byte-for-byte from the
// pre-split component.

export type InlineToolbarPopover = null | 'text-style' | 'color' | 'highlight' | 'link' | 'overflow'

interface LinkPopoverHandle {
  quill: any
}

interface LinkState {
  mode: 'insert' | 'edit'
  url: string
  pageSlug: string
  linkText: string
  openInNewTab: boolean
  savedRange: { index: number; length: number } | null
  originalText: string
}

export function useInlineLinkPopover(deps: {
  handle: ComputedRef<LinkPopoverHandle | null>
  store: { pages: PageRef[] }
  withQuill: <T>(fn: (q: any) => T) => T | undefined
  openPopover: Ref<InlineToolbarPopover>
  showPopoverAnchored: (kind: InlineToolbarPopover, anchor: HTMLElement, width?: number) => void
  recomputeFormatState: (rangeArg?: { index: number; length: number } | null) => void
}) {
  const { handle, store, withQuill, openPopover, showPopoverAnchored, recomputeFormatState } = deps

  const linkState = ref<LinkState>({
    mode: 'insert', url: '', pageSlug: '', linkText: '',
    openInNewTab: false, savedRange: null, originalText: '',
  })
  const linkUrlInput = ref<HTMLInputElement | null>(null)
  const pagePickerOpen = ref(false)
  const pageQuery = ref('')
  const pageHighlight = ref(0)

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

  // A bare email address in the URL field surfaces an explicit "make this a
  // mailto: link" offer — never converted automatically (session 363).
  const urlLooksLikeEmail = computed(() =>
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(linkState.value.url.trim())
  )

  function applyMailto(): void {
    if (!urlLooksLikeEmail.value) return
    linkState.value.url = 'mailto:' + linkState.value.url.trim()
    linkState.value.pageSlug = ''
  }

  return {
    linkState,
    linkUrlInput,
    pagePickerOpen,
    pageQuery,
    pageHighlight,
    filteredPages,
    openLinkPopover,
    saveLink,
    removeLink,
    cancelLinkPopover,
    closeLinkPopover,
    pickPage,
    onUrlInput,
    urlLooksLikeEmail,
    applyMailto,
  }
}
