import { watch, onMounted, onBeforeUnmount, nextTick, type Ref } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { useEditorStore } from '../stores/editor'
import type { Widget } from '../types'

// In-page text editing (session 304 Phase 2). Scans the selected,
// inline-eligible widget's server-rendered preview for [data-config-key]
// nodes and arms them: plaintext nodes become contenteditable; richtext
// nodes mount a no-toolbar Quill instance (formatting stays in the
// Inspector during A — documented interim). Every editor is SEEDED FROM
// THE RAW CONFIG VALUE and writes the raw value back to that config path;
// the rendered DOM is never serialized (it bakes resolved {{tokens}} and
// double-wraps inline images). Per-node {{token}} content is gated off.

const COMMIT_DEBOUNCE_MS = 300

function getByPath(root: any, path: string): any {
  let cur = root
  for (const seg of path.split('.')) {
    if (cur == null) return undefined
    cur = cur[seg]
  }
  return cur
}

export function useInlineEdit(
  htmlEl: Ref<HTMLElement | null>,
  widget: Ref<Widget>,
  isSelected: Ref<boolean>,
) {
  const store = useEditorStore()

  let activeNode: HTMLElement | null = null
  let activeQuill: any = null
  let activePath: string | null = null
  let activeCommit: (() => void) | null = null
  let outsideBound = false

  const armed: HTMLElement[] = []
  const handlers = new WeakMap<HTMLElement, (e: Event) => void>()

  function commit(path: string, value: string): void {
    store.updateLocalConfigPath(widget.value.id, path, value)
  }

  // Session 305: the in-place editor must survive losing DOM focus. With a
  // floating toolbar, focus legitimately moves to the bar (a font/heading
  // <select>, the link popover <input>); the old blur-teardown disposed the
  // editor the toolbar was acting on before it could be used. So the edit
  // session is not tied to focus at all — it ends only on a *deliberate*
  // exit: a pointer-down outside both this widget's preview and the shared
  // toolbar, Escape, deselecting the widget, switching to another inline
  // node, or unmount. Refresh-suppression while active is unchanged.
  function onDocPointerDown(e: Event): void {
    if (!activeNode) return
    const t = e.target as HTMLElement | null
    if (!t) return
    // Inside this widget's preview → field switching is handled by the
    // per-node activate handlers; clicking inert parts keeps the session.
    if (htmlEl.value && htmlEl.value.contains(t)) return
    // The one shared toolbar (and its link popover) — never an exit.
    if (t.closest && t.closest('[data-inline-toolbar]')) return
    teardownActive()
  }

  function onDocKeydown(e: KeyboardEvent): void {
    if (!activeNode) return
    if (e.key === 'Escape') teardownActive()
  }

  function bindOutsideClose(): void {
    if (outsideBound) return
    outsideBound = true
    // Capture phase so a click that lands outside still resolves before any
    // other handler can swallow it.
    document.addEventListener('pointerdown', onDocPointerDown, true)
    document.addEventListener('keydown', onDocKeydown, true)
  }

  function unbindOutsideClose(): void {
    if (!outsideBound) return
    outsideBound = false
    document.removeEventListener('pointerdown', onDocPointerDown, true)
    document.removeEventListener('keydown', onDocKeydown, true)
  }

  // reconcile=true  → leaving inline editing for real: flush + one
  //                   reconciling preview refresh so tokens / inline images
  //                   re-resolve for display (the v-html is replaced and
  //                   re-armed by the watch below).
  // reconcile=false → switching to another inline node in the SAME widget:
  //                   the value is already in local config via the
  //                   debounced commit, so skip the refresh — re-rendering
  //                   the v-html mid-switch would detach the node the next
  //                   editor is about to mount on. The eventual real exit
  //                   reconciles tokens/images.
  function teardownActive(reconcile = true): void {
    if (!activeNode) return
    // Persist the latest value before disposing — the per-keystroke commit
    // is debounced, so a fast exit (outside click / Escape / field switch)
    // could otherwise drop the last sub-300ms of edits.
    activeCommit?.()
    const node = activeNode
    const path = activePath
    const quill = activeQuill
    activeNode = null
    activeQuill = null
    activePath = null
    activeCommit = null
    unbindOutsideClose()
    if (quill) {
      // Replace the Quill chrome with the edited HTML so the field reads
      // correctly even when we deliberately skip the reconciling refresh.
      try { node.innerHTML = quill.root.innerHTML } catch { /* detached */ }
      // Drop the shared-toolbar handle so the one app-level toolbar hides /
      // re-targets. Guarded inside the store so a late teardown from a
      // superseded editor can't wipe a newer active one.
      store.clearActiveInlineEditor(quill)
    }
    node.removeAttribute('contenteditable')
    if (path && reconcile) {
      store.endInlineEdit(widget.value.id)
    }
  }

  function activatePlain(node: HTMLElement, path: string): void {
    const raw = getByPath(widget.value.config, path)
    node.setAttribute('contenteditable', 'plaintext-only')
    if (typeof raw === 'string' && node.innerText !== raw) {
      node.innerText = raw
    }
    activeNode = node
    activePath = path
    store.beginInlineEdit(widget.value.id)
    node.focus()
    // Explicit caret at end — focus() alone on a contenteditable doesn't
    // reliably show a usable cursor.
    const sel = window.getSelection()
    if (sel) {
      const r = document.createRange()
      r.selectNodeContents(node)
      r.collapse(false)
      sel.removeAllRanges()
      sel.addRange(r)
    }

    activeCommit = () => commit(path, node.innerText)
    const onInput = useDebounceFn(
      () => commit(path, node.innerText),
      COMMIT_DEBOUNCE_MS,
    )
    node.addEventListener('input', onInput)
    bindOutsideClose()
  }

  function activateRich(node: HTMLElement, path: string): void {
    const Quill = (window as any).Quill
    if (!Quill) {
      // No Quill on the page — fall back to plaintext entry rather than
      // serializing rendered DOM.
      activatePlain(node, path)
      return
    }
    const raw = getByPath(widget.value.config, path)
    node.innerHTML = ''
    const host = document.createElement('div')
    node.appendChild(host)

    // theme:'snow' matches the (working) Inspector RichTextField — its
    // CSS is already loaded globally, so the editor box is actually
    // usable. toolbar:false keeps formatting in the Inspector (A interim).
    const quill = new Quill(host, { theme: 'snow', modules: { toolbar: false } })
    if (typeof raw === 'string' && raw !== '') {
      // Seed from the raw stored value the same way RichTextField does.
      quill.root.innerHTML = raw
    }
    activeNode = node
    activeQuill = quill
    activePath = path
    // Publish to the shared-toolbar rendezvous BEFORE focusing so the one
    // app-level toolbar binds to this instance (constraint C2).
    store.setActiveInlineEditor({ quill, widgetId: widget.value.id, path })
    store.beginInlineEdit(widget.value.id)
    quill.focus()
    quill.setSelection(quill.getLength(), 0) // visible caret at end

    activeCommit = () => commit(path, quill.root.innerHTML)
    const onChange = useDebounceFn(() => {
      commit(path, quill.root.innerHTML)
    }, COMMIT_DEBOUNCE_MS)
    quill.on('text-change', (_d: any, _o: any, source: string) => {
      if (source === 'user') onChange()
    })
    bindOutsideClose()
  }

  function disarm(): void {
    teardownActive()
    for (const node of armed.splice(0)) {
      node.classList.remove('inline-editable', 'inline-editable--text')
      node.removeAttribute('contenteditable')
      const h = handlers.get(node)
      if (h) {
        node.removeEventListener('pointerdown', h)
        handlers.delete(node)
      }
    }
  }

  function arm(): void {
    disarm()
    if (!htmlEl.value) return
    if (!isSelected.value) return
    if (!widget.value.widget_type_inline_editable) return

    const nodes = htmlEl.value.querySelectorAll<HTMLElement>('[data-config-key]')
    nodes.forEach((node) => {
      const path = node.dataset.configKey
      if (!path) return
      const type = node.dataset.configType === 'richtext' ? 'richtext' : 'text'

      // Token-bearing prose is editable: the editor seeds from and writes
      // the RAW config value, so a {{token}} shows literally and round-
      // trips literally — substitution still happens at render. (Owner
      // decision, session 304: show the token in-place rather than push
      // the user to the Inspector.) The genuinely-unsafe data-driven
      // {{item.*}} templates are excluded at the WIDGET level via
      // inlineEditable()=false, not here.

      node.classList.add('inline-editable')
      if (type === 'text') node.classList.add('inline-editable--text')

      const activate = (e: Event) => {
        if (activeNode === node) return
        e.stopPropagation()
        // Switching nodes within this widget — no reconciling refresh, so
        // the v-html isn't swapped out from under the editor we're about
        // to mount.
        teardownActive(false)
        // Mount AFTER the triggering gesture finishes. Rebuilding the
        // node's DOM and focusing synchronously inside the pointerdown the
        // user is mid-click on makes the caret fail to land (press/up
        // resolve against swapped-out DOM). This was the bug.
        requestAnimationFrame(() => {
          if (type === 'richtext') activateRich(node, path)
          else activatePlain(node, path)
        })
      }
      handlers.set(node, activate)
      node.addEventListener('pointerdown', activate)
      armed.push(node)
    })
  }

  // Re-arm after every preview re-render and on selection / eligibility
  // changes. flush:'post' + nextTick so the v-html DOM is in place.
  // While an editor is actively open, a preview refresh must NOT disarm it
  // (that nuked the live editor + toolbar mid-edit). The save echo is
  // already suppressed for the active widget; any other refresh is held off
  // until the session ends deliberately, at which point teardownActive()
  // nulls activeNode then runs the one reconciling refresh, and this watch
  // re-arms cleanly.
  watch(
    [
      () => widget.value.preview_html,
      isSelected,
      () => widget.value.widget_type_inline_editable,
    ],
    async () => {
      if (activeNode) return
      await nextTick()
      arm()
    },
    { flush: 'post' },
  )

  // Deselecting the widget (e.g. selecting another via the layer menu, with
  // no pointer-down in this preview) ends the session deterministically.
  watch(isSelected, (sel) => {
    if (!sel && activeNode) teardownActive()
  })

  onMounted(async () => {
    await nextTick()
    arm()
  })

  onBeforeUnmount(() => {
    disarm()
  })
}
