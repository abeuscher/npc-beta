<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue'
import type { FieldDef, Widget } from '../../types'
import { useEditorStore } from '../../stores/editor'

const props = defineProps<{
  field: FieldDef
  widget: Widget
  modelValue: any
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const store = useEditorStore()
const editorEl = ref<HTMLElement | null>(null)
const currentUrl = typeof window !== 'undefined' ? window.location.pathname + window.location.search : ''
let quillInstance: any = null
let suppressChange = false

onMounted(() => {
  if (!editorEl.value) return

  // Quill is loaded globally via CDN (already on the page via Filament)
  const Quill = (window as any).Quill
  if (!Quill) {
    console.error('Quill not found on window')
    return
  }

  quillInstance = new Quill(editorEl.value, {
    theme: 'snow',
    modules: {
      toolbar: {
        container: [
          [{ header: [1, 2, 3, 4, 5, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ color: [] }, { background: [] }],
          [{ list: 'bullet' }, { list: 'ordered' }],
          ['blockquote'],
          [{ align: ['', 'center', 'right', 'justify'] }],
          ['link', 'image'],
          ['clean'],
        ],
        handlers: {
          image: handleImageUpload,
        },
      },
    },
  })

  if (props.modelValue) {
    quillInstance.root.innerHTML = props.modelValue
  }

  quillInstance.on('text-change', () => {
    if (suppressChange) return
    emit('update:modelValue', quillInstance.root.innerHTML)
  })
})

onUnmounted(() => {
  quillInstance = null
})

// Sync external value changes into Quill (guard against echo loops).
// When the widget is dirty, the local Quill state is by definition newer
// than any modelValue arriving from the store, so skip the assignment
// entirely — this prevents the editor from being wiped back to a stale
// server snapshot mid-typing.
watch(
  () => props.modelValue,
  (newVal) => {
    if (!quillInstance) return
    if (store.isWidgetDirty(props.widget.id)) return
    const current = quillInstance.root.innerHTML
    if (current !== newVal) {
      suppressChange = true
      quillInstance.root.innerHTML = newVal ?? ''
      suppressChange = false
    }
  }
)

function handleImageUpload() {
  const uploadUrl = store.inlineImageUploadUrl
  if (!uploadUrl) return

  const input = document.createElement('input')
  input.type = 'file'
  input.accept = 'image/png,image/jpeg,image/gif,image/webp,image/svg+xml'
  input.onchange = async () => {
    const file = input.files?.[0]
    if (!file || !quillInstance) return

    const form = new FormData()
    form.append('file', file)
    form.append('model_type', 'page_widget')
    form.append('model_id', props.widget.id)

    try {
      const csrfMeta = document.head.querySelector('meta[name=csrf-token]') as HTMLMetaElement | null
      const res = await fetch(uploadUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfMeta?.content ?? '',
        },
        body: form,
      })
      const data = await res.json()
      if (data.url) {
        const range = quillInstance.getSelection(true)
        quillInstance.insertEmbed(range.index, 'image', data.url)
        quillInstance.setSelection(range.index + 1)
      }
    } catch (e) {
      console.error('Inline image upload failed', e)
    }
  }
  input.click()
}
</script>

<template>
  <div class="inspector-richtext">
    <a
      v-if="store.themeEditorUrl"
      class="inspector-richtext__theme-link"
      :href="`${store.themeEditorUrl}&return_to=${encodeURIComponent(currentUrl)}`"
    >Edit site styles →</a>
    <div ref="editorEl"></div>
  </div>
</template>

<style scoped>
.inspector-richtext {
  position: relative;
}

.inspector-richtext__theme-link {
  display: block;
  padding: 0.25rem 0.25rem 0.375rem;
  font-size: 0.6875rem;
  color: #6b7280;
  text-align: right;
  text-decoration: none;
}

.inspector-richtext__theme-link:hover {
  color: #2563eb;
}

.inspector-richtext :deep(.ql-container) {
  font-size: 0.875rem;
}

.inspector-richtext :deep(.ql-editor) {
  min-height: 120px;
}
</style>
