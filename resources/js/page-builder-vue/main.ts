import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'

const mountEl = document.getElementById('page-builder-app')

if (mountEl) {
  const app = createApp(App)
  app.use(createPinia())
  app.mount(mountEl)
}
