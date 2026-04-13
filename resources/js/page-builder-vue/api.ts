import type {
  CreateWidgetPayload,
  UpdateWidgetPayload,
  CreateLayoutPayload,
  UpdateLayoutPayload,
  ReorderItem,
  TreeResponse,
  Widget,
  PageLayout,
  PageItem,
  WidgetType,
  WidgetDraftPreset,
  Collection,
  Tag,
  PageRef,
  EventRef,
} from './types'

let csrfToken = ''
let baseUrl = ''

export function configure(token: string, url: string): void {
  csrfToken = token
  baseUrl = url.replace(/\/$/, '')
}

export class ApiError extends Error {
  status: number
  body: string

  constructor(message: string, status: number, body: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.body = body
  }
}

async function request<T>(
  method: string,
  path: string,
  body?: any,
  signal?: AbortSignal
): Promise<T> {
  const url = `${baseUrl}/${path.replace(/^\//, '')}`

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-CSRF-TOKEN': csrfToken,
  }

  const init: RequestInit = { method, headers, credentials: 'same-origin' }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    init.body = JSON.stringify(body)
  }

  if (signal) {
    init.signal = signal
  }

  const res = await fetch(url, init)

  if (!res.ok) {
    const text = await res.text().catch(() => '')
    let message = text
    try {
      const parsed = JSON.parse(text)
      message = parsed?.error ?? parsed?.message ?? text
    } catch {
      // not JSON — use raw text
    }
    if (!message) message = `${method} ${path} failed (${res.status})`
    throw new ApiError(message, res.status, text)
  }

  return res.json()
}

// Widget CRUD
export function getWidgets(pageId: string): Promise<TreeResponse> {
  return request('GET', `${pageId}/widgets`)
}

export function createWidget(
  pageId: string,
  payload: CreateWidgetPayload
): Promise<{ widget: Widget; items: PageItem[]; required_libs: string[] }> {
  return request('POST', `${pageId}/widgets`, payload)
}

export function updateWidget(
  widgetId: string,
  payload: UpdateWidgetPayload
): Promise<{ widget: Widget }> {
  return request('PUT', `widgets/${widgetId}`, payload)
}

export function deleteWidget(
  widgetId: string
): Promise<{ deleted: boolean; items: PageItem[]; required_libs: string[] }> {
  return request('DELETE', `widgets/${widgetId}`)
}

export function copyWidget(
  widgetId: string
): Promise<{ widget: Widget; items: PageItem[]; required_libs: string[] }> {
  return request('POST', `widgets/${widgetId}/copy`)
}

export function reorderWidgets(
  pageId: string,
  items: ReorderItem[]
): Promise<{ items: PageItem[]; required_libs: string[] }> {
  return request('PUT', `${pageId}/widgets/reorder`, { items })
}

// Layout CRUD
export function createLayout(
  pageId: string,
  payload: CreateLayoutPayload
): Promise<{ layout: PageLayout; items: PageItem[]; required_libs: string[] }> {
  return request('POST', `${pageId}/layouts`, payload)
}

export function updateLayout(
  layoutId: string,
  payload: UpdateLayoutPayload
): Promise<{ layout: PageLayout }> {
  return request('PUT', `layouts/${layoutId}`, payload)
}

export function deleteLayout(
  layoutId: string
): Promise<{ deleted: boolean; items: PageItem[]; required_libs: string[] }> {
  return request('DELETE', `layouts/${layoutId}`)
}

// Preview
export function getPreview(
  widgetId: string,
  signal?: AbortSignal
): Promise<{ id: string; html: string; required_libs: string[] }> {
  return request('GET', `widgets/${widgetId}/preview`, undefined, signal)
}

// Lookups
export function getWidgetTypes(
  pageType: string
): Promise<{ widget_types: WidgetType[] }> {
  return request('GET', `widget-types?page_type=${encodeURIComponent(pageType)}`)
}

export function getCollections(): Promise<{ collections: Collection[] }> {
  return request('GET', 'collections')
}

export function getCollectionFields(
  handle: string
): Promise<{ fields: { key: string; label: string; type: string }[] }> {
  return request('GET', `collections/${encodeURIComponent(handle)}/fields`)
}

export function getTags(): Promise<{ tags: Tag[] }> {
  return request('GET', 'tags')
}

export function getPages(): Promise<{ pages: PageRef[] }> {
  return request('GET', 'pages')
}

export function getEvents(): Promise<{ events: EventRef[] }> {
  return request('GET', 'events')
}

export function getDataSource(
  source: string
): Promise<{ options: Record<string, string> }> {
  return request('GET', `data-sources/${encodeURIComponent(source)}`)
}

// Image upload
export async function uploadImage(
  widgetId: string,
  key: string,
  file: File
): Promise<{ media_id: number; url: string }> {
  const url = `${baseUrl}/widgets/${widgetId}/image`
  const formData = new FormData()
  formData.append('key', key)
  formData.append('file', file)

  const res = await fetch(url, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken,
    },
    credentials: 'same-origin',
    body: formData,
  })

  if (!res.ok) {
    const text = await res.text().catch(() => '')
    throw new Error(`Image upload failed (${res.status}): ${text}`)
  }

  return res.json()
}

export async function removeImage(
  widgetId: string,
  key: string
): Promise<{ removed: boolean }> {
  return request('DELETE', `widgets/${widgetId}/image/${encodeURIComponent(key)}`)
}

// Appearance background image
export async function uploadAppearanceImage(
  widgetId: string,
  file: File
): Promise<{ url: string }> {
  const url = `${baseUrl}/widgets/${widgetId}/appearance-image`
  const formData = new FormData()
  formData.append('file', file)

  const res = await fetch(url, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken,
    },
    credentials: 'same-origin',
    body: formData,
  })

  if (!res.ok) {
    const text = await res.text().catch(() => '')
    throw new Error(`Appearance image upload failed (${res.status}): ${text}`)
  }

  return res.json()
}

export async function removeAppearanceImage(
  widgetId: string
): Promise<{ removed: boolean }> {
  return request('DELETE', `widgets/${widgetId}/appearance-image`)
}

// Color swatches
export function saveColorSwatches(
  swatches: string[]
): Promise<{ swatches: string[] }> {
  return request('PUT', 'color-swatches', { swatches })
}

// Widget presets (designer drafts)
export function createDraftPreset(
  widgetTypeId: string,
  widgetId: string
): Promise<{ preset: WidgetDraftPreset }> {
  return request('POST', 'widget-presets', {
    widget_type_id: widgetTypeId,
    widget_id: widgetId,
  })
}

export function updateDraftPreset(
  presetId: string,
  payload: { label?: string; description?: string | null; handle?: string }
): Promise<{ preset: WidgetDraftPreset }> {
  return request('PATCH', `widget-presets/${presetId}`, payload)
}

export function deleteDraftPreset(
  presetId: string
): Promise<{ deleted: boolean }> {
  return request('DELETE', `widget-presets/${presetId}`)
}
