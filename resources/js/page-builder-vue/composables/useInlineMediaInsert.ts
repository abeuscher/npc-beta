import { onBeforeUnmount, ref, type ComputedRef } from 'vue'
import { openHeroiconPicker, setHeroiconsUrl } from '../../admin/heroicon-picker.js'

// Session 347 — media-insertion seam extracted from InlineFormatToolbar.vue
// (§F7). Owns the two embed paths that aren't text formatting: the async image
// upload (file picker → POST → insertEmbed) and the heroicon picker, plus the
// transient error toast they share. The orchestrator owns text-format dispatch;
// this is the separate "insert non-text content" concern. Behaviour is
// preserved byte-for-byte from the pre-split component.

interface MediaHandle {
  quill: any
  widgetId: string
}

export function useInlineMediaInsert(deps: {
  handle: ComputedRef<MediaHandle | null>
  store: { inlineImageUploadUrl?: string; heroiconsUrl?: string }
  withQuill: <T>(fn: (q: any) => T) => T | undefined
}) {
  const { handle, store, withQuill } = deps

  const imageUploading = ref(false)
  const errorToast = ref('')
  let errorToastTimer: ReturnType<typeof setTimeout> | null = null
  let imageInput: HTMLInputElement | null = null

  function showError(msg: string): void {
    errorToast.value = msg
    if (errorToastTimer) clearTimeout(errorToastTimer)
    errorToastTimer = setTimeout(() => {
      errorToast.value = ''
      errorToastTimer = null
    }, 4000)
  }

  function openImage(): void {
    const h = handle.value
    if (!h) return
    if (!imageInput) {
      imageInput = document.createElement('input')
      imageInput.type = 'file'
      imageInput.accept = 'image/png,image/jpeg,image/gif,image/webp,image/svg+xml'
      imageInput.style.display = 'none'
      document.body.appendChild(imageInput)
      imageInput.addEventListener('change', onImageChosen)
    }
    imageInput.value = ''
    imageInput.click()
  }

  async function onImageChosen(): Promise<void> {
    const file = imageInput?.files?.[0]
    if (!file) return
    const h = handle.value
    if (!h) return
    const q = h.quill
    let range: any = null
    try { range = q.getSelection() } catch { /* selection lost */ }
    if (!range) {
      try { range = { index: q.getLength() - 1, length: 0 } } catch { range = { index: 0, length: 0 } }
    }
    const uploadUrl = store.inlineImageUploadUrl
    if (!uploadUrl) return
    imageUploading.value = true
    try {
      const form = new FormData()
      form.append('file', file)
      form.append('model_type', 'page_widget')
      form.append('model_id', h.widgetId)
      const csrfMeta = document.head.querySelector('meta[name=csrf-token]') as HTMLMetaElement | null
      const res = await fetch(uploadUrl, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfMeta?.content ?? '' },
        body: form,
      })
      const data = await res.json()
      if (data.url) {
        q.insertEmbed(range.index, 'image', data.url, 'user')
        q.setSelection(range.index + 1, 0, 'user')
      } else {
        showError('Image upload failed.')
      }
    } catch (e) {
      console.error('Inline image upload failed', e)
      showError('Image upload failed.')
    } finally {
      imageUploading.value = false
    }
  }

  function openHeroicon(anchor: HTMLElement): void {
    const h = handle.value
    if (!h) return
    if (store.heroiconsUrl) setHeroiconsUrl(store.heroiconsUrl)
    const q = h.quill
    let range: any = null
    try { range = q.getSelection() } catch { /* selection lost */ }
    if (!range) {
      try { range = { index: q.getLength() - 1, length: 0 } } catch { range = { index: 0, length: 0 } }
    }
    openHeroiconPicker(anchor, (icon: { name: string; svg: string }) => {
      withQuill((qq) => {
        const Quill = (window as any).Quill
        qq.insertEmbed(range.index, 'heroicon', icon, Quill?.sources?.USER ?? 'user')
        qq.setSelection(range.index + 1, 0, Quill?.sources?.SILENT ?? 'silent')
      })
    })
  }

  onBeforeUnmount(() => {
    if (errorToastTimer) clearTimeout(errorToastTimer)
    if (imageInput && imageInput.parentNode) imageInput.parentNode.removeChild(imageInput)
  })

  return { openImage, openHeroicon, imageUploading, errorToast }
}
