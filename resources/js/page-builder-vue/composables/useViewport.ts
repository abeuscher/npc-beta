import { ref, computed } from 'vue'
import { useEditorStore } from '../stores/editor'

export interface ViewportPreset {
  width: number
  label: string
}

export const viewportPresets: ViewportPreset[] = [
  { width: 1920, label: 'Desktop' },
  { width: 1024, label: 'Tablet' },
  { width: 375, label: 'Mobile' },
]

// The preset itself lives in the editor store (lifted so the canvas control
// bar, the canvas zoom, and breakpoint-aware inspector controls all share one
// source of truth); this composable keeps only the per-canvas pane
// measurement and the zoom derivation.
export function useViewport() {
  const store = useEditorStore()
  const paneWidth = ref(0)

  const zoomFactor = computed(() =>
    paneWidth.value > 0 ? Math.min(1, paneWidth.value / store.presetViewport) : 1
  )

  function computeZoom(width: number): void {
    paneWidth.value = width
  }

  return {
    zoomFactor,
    viewportPresets,
    computeZoom,
  }
}
