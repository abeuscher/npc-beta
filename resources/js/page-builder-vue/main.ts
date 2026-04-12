import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import './styles/inspector.css'
import type { BootstrapData } from './types'

function mountPageBuilder(el: HTMLElement): void {
  if (el.dataset.mounted === 'true') return

  const raw = el.getAttribute('data-bootstrap')
  if (!raw) return

  let bootstrap: BootstrapData
  try {
    bootstrap = JSON.parse(raw)
  } catch (e) {
    console.error('page-builder: failed to parse bootstrap data', e)
    return
  }

  const app = createApp(App, { bootstrap })
  app.use(createPinia())
  app.mount(el)
  el.dataset.mounted = 'true'
}

const elements = document.querySelectorAll<HTMLElement>('[data-page-builder-app]')
elements.forEach(mountPageBuilder)
