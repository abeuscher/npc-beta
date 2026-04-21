export function scrollSelectionIntoCentre(
  id: string,
  type: 'widget' | 'layout',
): void {
  const selector = type === 'layout'
    ? `[data-layout-id="${CSS.escape(id)}"]`
    : `[data-widget-id="${CSS.escape(id)}"]`

  const el = document.querySelector<HTMLElement>(selector)
  if (!el) return

  const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

  el.scrollIntoView({
    block: 'center',
    inline: 'nearest',
    behavior: reduceMotion ? 'auto' : 'smooth',
  })
}
