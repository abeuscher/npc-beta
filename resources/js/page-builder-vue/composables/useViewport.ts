import { ref, computed } from 'vue'

export interface ViewportPreset {
  width: number
  label: string
}

export const viewportPresets: ViewportPreset[] = [
  { width: 1920, label: 'Desktop' },
  { width: 1024, label: 'Tablet' },
  { width: 375, label: 'Mobile' },
]

export function useViewport() {
  const presetViewport = ref(1920)
  const paneWidth = ref(0)

  const zoomFactor = computed(() =>
    paneWidth.value > 0 ? Math.min(1, paneWidth.value / presetViewport.value) : 1
  )

  function computeZoom(width: number): void {
    paneWidth.value = width
  }

  function setViewport(width: number): void {
    presetViewport.value = width
  }

  return {
    presetViewport,
    zoomFactor,
    viewportPresets,
    computeZoom,
    setViewport,
  }
}
