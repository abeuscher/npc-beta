import { ref, type ComputedRef, type Ref } from 'vue'

// Session 347 — position/measurement seam extracted from InlineFormatToolbar.vue
// (session 306). Owns the toolbar's geometry: where the floating bar sits
// relative to the active field (the C3–C10 flip/clamp rules from
// docs/inline-formatting-toolbar-spec.md §C), where an anchored popover opens,
// and the responsive-collapse measurement. The orchestrator owns WHEN to
// recompute (scroll/resize/ResizeObserver wiring, the handle lifecycle) and
// WHICH popover is open; this composable is the pure geometry it calls into.
// Behaviour is preserved byte-for-byte from the pre-split component.

interface PositionHandle {
  getRect: () => DOMRect
}

export function useInlineToolbarPosition(
  handle: ComputedRef<PositionHandle | null>,
  barEl: Ref<HTMLElement | null>,
) {
  // Bar placement.
  const top = ref(0)
  const left = ref(0)
  const onScreen = ref(false)

  // Anchored-popover placement.
  const popoverTop = ref(0)
  const popoverLeft = ref(0)
  const popoverFlipped = ref(false)

  // Responsive collapse (see measureBar).
  const collapseStep = ref<0 | 1 | 2 | 3 | 4>(0)
  const wrapped = ref(false)

  let positionFrame = 0

  function getCanvasRect(): { left: number; right: number; top: number } {
    const main = document.querySelector('.vue-editor__main') as HTMLElement | null
    const inspector = document.querySelector('.vue-editor__inspector') as HTMLElement | null
    if (main) {
      const r = main.getBoundingClientRect()
      const ir = inspector?.getBoundingClientRect()
      const right = ir ? ir.left - 8 : (window.innerWidth - 8)
      return { left: r.left, right, top: r.top }
    }
    return { left: 8, right: window.innerWidth - 8, top: 0 }
  }

  function updatePosition(): void {
    const h = handle.value
    const bar = barEl.value
    if (!h || !bar) return
    const fr = h.getRect()
    const bw = bar.offsetWidth || 700
    const bh = bar.offsetHeight || 38
    const canvas = getCanvasRect()
    const vpTop = 8
    const vpBottom = window.innerHeight - 8

    // C9 / C10: hide when field is entirely above or below the viewport
    if (fr.bottom < 0 || fr.top > window.innerHeight) {
      onScreen.value = false
      return
    }
    onScreen.value = true

    // Default: above the field, 8px gap, left-aligned to field's left
    let t = fr.top - bh - 8
    let l = fr.left

    // C3: flip below if natural top is too high
    if (t < vpTop) {
      t = fr.bottom + 8
    }

    // C8: while the field's top is above viewport top and bottom is below,
    // pin to viewport top.
    if (fr.top < vpTop && fr.bottom > vpTop) {
      t = vpTop
    }

    // C4: clamp right
    if (l + bw > canvas.right) {
      l = canvas.right - bw
    }
    // C5: clamp left
    if (l < canvas.left + 8) {
      l = canvas.left + 8
    }

    // C10 check after positioning
    if (t > vpBottom) {
      onScreen.value = false
      return
    }

    top.value = t
    left.value = l
  }

  function requestPositionUpdate(): void {
    if (positionFrame) cancelAnimationFrame(positionFrame)
    positionFrame = requestAnimationFrame(() => {
      positionFrame = 0
      updatePosition()
    })
  }

  // §J responsive collapse. Naive width budgets per group; recompute the
  // collapse step from the container's available width on every measure tick.
  // Two-row default layout: keep all 18 controls visible and let the bar wrap
  // to multiple rows via CSS flex-wrap + max-width. The responsive collapse
  // ladder from spec §J is intentionally not engaged here — the user prefers a
  // compact two-row bar over hiding groups behind overflow. The collapse-ladder
  // math is retained in version control for a future amend if the wrap form
  // proves too tall.
  function measureBar(): void {
    collapseStep.value = 0
    wrapped.value = false
  }

  // Anchored-popover geometry: place a popover of `width` below its trigger,
  // flipping above when it would overflow the viewport bottom, clamped into the
  // viewport horizontally. Sets popoverTop/Left/Flipped; the orchestrator owns
  // openPopover/popoverAnchor and calls this inside nextTick once the trigger
  // is laid out.
  function positionPopover(anchor: HTMLElement, width = 240): void {
    const r = anchor.getBoundingClientRect()
    const ph = 240 // approximate popover height budget; flips when exceeding viewport
    let pt = r.bottom + 4
    let flipped = false
    if (pt + ph > window.innerHeight - 8) {
      pt = r.top - ph - 4
      flipped = true
    }
    let pl = r.left
    if (pl + width > window.innerWidth - 8) {
      pl = window.innerWidth - 8 - width
    }
    if (pl < 8) pl = 8
    popoverTop.value = pt
    popoverLeft.value = pl
    popoverFlipped.value = flipped
  }

  return {
    top,
    left,
    onScreen,
    popoverTop,
    popoverLeft,
    popoverFlipped,
    collapseStep,
    wrapped,
    updatePosition,
    requestPositionUpdate,
    measureBar,
    positionPopover,
  }
}
