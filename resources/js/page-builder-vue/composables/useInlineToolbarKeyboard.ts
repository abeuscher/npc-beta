import { onBeforeUnmount, onMounted, ref, type ComputedRef } from 'vue'

// Session 347 — keyboard / accessibility seam extracted from
// InlineFormatToolbar.vue (§K). Owns the roving-tabindex registry over the bar
// buttons, the in-toolbar arrow/Home/End/Escape navigation, and the two global
// shortcuts driven from inside the active editor (Alt+F10 enters the toolbar,
// Cmd/Ctrl+K opens the link popover). It binds its own window listener for the
// life of the component. Behaviour is preserved byte-for-byte from the
// pre-split component.

interface KeyboardHandle {
  quill: any
  hostEl: HTMLElement
}

export function useInlineToolbarKeyboard(deps: {
  handle: ComputedRef<KeyboardHandle | null>
  openLinkPopover: (anchor: HTMLElement) => void
}) {
  const { handle, openLinkPopover } = deps

  // Roving tabindex within the toolbar buttons.
  const focusedIdx = ref(0)
  const buttonRefs = ref<HTMLElement[]>([])

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

  onMounted(() => {
    window.addEventListener('keydown', onWindowKeydown, true)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('keydown', onWindowKeydown, true)
  })

  return { focusedIdx, registerBtn, onToolbarKeydown }
}
