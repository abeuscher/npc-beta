import { createApp } from 'vue'
import { createPinia } from 'pinia'
import TypographyPanel from './TypographyPanel.vue'
import type { TypographyBootstrap } from './types'

function mount(el: HTMLElement): void {
  if (el.dataset.mounted === 'true') return
  const raw = el.getAttribute('data-bootstrap')
  if (!raw) return

  let bootstrap: TypographyBootstrap
  try {
    bootstrap = JSON.parse(raw)
  } catch (e) {
    console.error('theme-editor: failed to parse bootstrap data', e)
    return
  }

  const app = createApp(TypographyPanel, { bootstrap })
  app.use(createPinia())
  app.mount(el)
  el.dataset.mounted = 'true'
}

function mountAll() {
  document.querySelectorAll<HTMLElement>('[data-theme-editor-app]').forEach(mount)
}

mountAll()

// Re-scan whenever Livewire morphs new nodes into the DOM (e.g. tab switch on
// the Design System page renders the Vue mount element only after the tab becomes active).
const observer = new MutationObserver((mutations) => {
  for (const m of mutations) {
    for (const node of Array.from(m.addedNodes)) {
      if (!(node instanceof HTMLElement)) continue
      if (node.matches?.('[data-theme-editor-app]')) {
        mount(node)
      } else {
        node.querySelectorAll?.<HTMLElement>('[data-theme-editor-app]').forEach(mount)
      }
    }
  }
})
observer.observe(document.body, { childList: true, subtree: true })

document.addEventListener('livewire:navigated', mountAll)
