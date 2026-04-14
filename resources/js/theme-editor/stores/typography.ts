import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { TypographyState, TypographyBootstrap } from '../types'

const DEBOUNCE_MS = 400

export const useTypographyStore = defineStore('typography', () => {
  const state = ref<TypographyState | null>(null)
  const bootstrap = ref<TypographyBootstrap | null>(null)
  const saving = ref(false)
  const saveError = ref<string | null>(null)
  const lastSavedAt = ref<number | null>(null)

  let saveTimer: ReturnType<typeof setTimeout> | null = null

  function init(data: TypographyBootstrap) {
    bootstrap.value = data
    state.value = data.typography
  }

  function queueSave() {
    if (saveTimer) clearTimeout(saveTimer)
    saveTimer = setTimeout(flush, DEBOUNCE_MS)
  }

  async function flush() {
    if (!state.value || !bootstrap.value) return
    saving.value = true
    saveError.value = null
    try {
      const res = await fetch(bootstrap.value.saveUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': bootstrap.value.csrfToken,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ typography: state.value }),
      })
      if (!res.ok) {
        const text = await res.text()
        saveError.value = `Save failed (${res.status}): ${text.slice(0, 200)}`
      } else {
        lastSavedAt.value = Date.now()
      }
    } catch (e: any) {
      saveError.value = e?.message ?? 'Save failed'
    } finally {
      saving.value = false
    }
  }

  return { state, bootstrap, saving, saveError, lastSavedAt, init, queueSave, flush }
})
