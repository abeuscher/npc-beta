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

  const armed: HTMLElement[] = []
  const handlers = new WeakMap<HTMLElement, (e: Event) => void>()

  function commit(path: string, value: string): void {
    store.updateLocalConfigPath(widget.value.id, path, value)
  }

  function teardownActive(): void {
    if (!activeNode) return
    const node = activeNode
    const path = activePath
    activeNode = null
    activeQuill = null
    activePath = null
    node.removeAttribute('contenteditable')
    if (path) {
      // Flush + one reconciling refresh so the preview re-derives from the
      // saved raw config (tokens / inline images re-applied). This replaces
      // the v-html, which re-arms via the watch below.
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

    const onInput = useDebounceFn(
      () => commit(path, node.innerText),
      COMMIT_DEBOUNCE_MS,
    )
    node.addEventListener('input', onInput)
    node.addEventListener(
      'blur',
      () => {
        commit(path, node.innerText)
        teardownActive()
      },
      { once: true },
    )
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

    const quill = new Quill(host, { modules: { toolbar: false } })
    if (typeof raw === 'string' && raw !== '') {
      // Seed from the raw stored value the same way RichTextField does.
      quill.root.innerHTML = raw
    }
    activeNode = node
    activeQuill = quill
    activePath = path
    store.beginInlineEdit(widget.value.id)
    quill.focus()

    const onChange = useDebounceFn(() => {
      commit(path, quill.root.innerHTML)
    }, COMMIT_DEBOUNCE_MS)
    quill.on('text-change', (_d: any, _o: any, source: string) => {
      if (source === 'user') onChange()
    })
    quill.root.addEventListener(
      'blur',
      () => {
        commit(path, quill.root.innerHTML)
        teardownActive()
      },
      { once: true },
    )
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
      const raw = getByPath(widget.value.config, path)

      // Mandatory safety gate: never edit a node whose raw stored value
      // carries a {{token}} — substitution runs before render, so an edit
      // would bake the resolved value and destroy the template.
      if (typeof raw === 'string' && raw.includes('{{')) return

      node.classList.add('inline-editable')
      if (type === 'text') node.classList.add('inline-editable--text')

      const activate = (e: Event) => {
        if (activeNode === node) return
        teardownActive()
        e.stopPropagation()
        if (type === 'richtext') activateRich(node, path)
        else activatePlain(node, path)
      }
      handlers.set(node, activate)
      node.addEventListener('pointerdown', activate)
      armed.push(node)
    })
  }

  // Re-arm after every preview re-render and on selection / eligibility
  // changes. flush:'post' + nextTick so the v-html DOM is in place.
  watch(
    [
      () => widget.value.preview_html,
      isSelected,
      () => widget.value.widget_type_inline_editable,
    ],
    async () => {
      await nextTick()
      arm()
    },
    { flush: 'post' },
  )

  onMounted(async () => {
    await nextTick()
    arm()
  })

  onBeforeUnmount(() => {
    disarm()
  })
}
