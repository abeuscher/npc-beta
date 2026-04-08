import type {
  CreateWidgetPayload,
  UpdateWidgetPayload,
  ReorderItem,
  TreeResponse,
  Widget,
  WidgetType,
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

async function request<T>(method: string, path: string, body?: any): Promise<T> {
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

  const res = await fetch(url, init)

  if (!res.ok) {
    const text = await res.text().catch(() => '')
    throw new Error(`API ${method} ${path} failed (${res.status}): ${text}`)
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
): Promise<{ widget: Widget; tree: Widget[]; required_libs: string[] }> {
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
): Promise<{ deleted: boolean; tree: Widget[]; required_libs: string[] }> {
  return request('DELETE', `widgets/${widgetId}`)
}

export function copyWidget(
  widgetId: string
): Promise<{ widget: Widget; tree: Widget[]; required_libs: string[] }> {
  return request('POST', `widgets/${widgetId}/copy`)
}

export function reorderWidgets(
  pageId: string,
  items: ReorderItem[]
): Promise<{ tree: Widget[]; required_libs: string[] }> {
  return request('PUT', `${pageId}/widgets/reorder`, { items })
}

// Preview
export function getPreview(
  widgetId: string
): Promise<{ id: string; html: string; required_libs: string[] }> {
  return request('GET', `widgets/${widgetId}/preview`)
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
