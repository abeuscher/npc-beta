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
let lookupUrl = ''

/**
 * Configure the API client.
 * - `url`: owner-scoped base (e.g. `/admin/api/page-builder/pages/{uuid}`).
 *   Used for widget/layout CRUD under a specific owner.
 * - `lookup`: owner-agnostic lookup base (e.g. `/admin/api/page-builder`).
 *   Used for widget-types, collections, tags, pages, events, data-sources,
 *   widget-presets, widget-defaults, color-swatches, and widget- and
 *   layout-keyed routes (update/delete/copy/preview/image). Defaults to the
 *   parent of `url` when omitted.
 */
export function configure(token: string, url: string, lookup?: string): void {
  csrfToken = token
  baseUrl = url.replace(/\/$/, '')
  lookupUrl = (lookup ?? baseUrl.replace(/\/(pages|templates)\/[^/]+$/, '')).replace(/\/$/, '')
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
  return requestAt<T>(baseUrl, method, path, body, signal)
}

async function lookup<T>(
  method: string,
  path: string,
  body?: any,
  signal?: AbortSignal
): Promise<T> {
  return requestAt<T>(lookupUrl, method, path, body, signal)
}

async function requestAt<T>(
  base: string,
  method: string,
  path: string,
  body?: any,
  signal?: AbortSignal
): Promise<T> {
  const url = `${base}/${path.replace(/^\//, '')}`

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

// Widget CRUD — owner-scoped (baseUrl already encodes the owner).
export function getWidgets(_ownerKey?: string): Promise<TreeResponse> {
  return request('GET', 'widgets')
}

export function createWidget(
  _ownerKey: string | undefined,
  payload: CreateWidgetPayload
): Promise<{ widget: Widget; items: PageItem[]; required_libs: string[] }> {
  return request('POST', 'widgets', payload)
}

export function updateWidget(
  widgetId: string,
  payload: UpdateWidgetPayload
): Promise<{ widget: Widget }> {
  return lookup('PUT', `widgets/${widgetId}`, payload)
}

export function deleteWidget(
  widgetId: string
): Promise<{ deleted: boolean; items: PageItem[]; required_libs: string[] }> {
  return lookup('DELETE', `widgets/${widgetId}`)
}

export function copyWidget(
  widgetId: string
): Promise<{ widget: Widget; items: PageItem[]; required_libs: string[] }> {
  return lookup('POST', `widgets/${widgetId}/copy`)
}

export function reorderWidgets(
  _ownerKey: string | undefined,
  items: ReorderItem[]
): Promise<{ items: PageItem[]; required_libs: string[] }> {
  return request('PUT', 'widgets/reorder', { items })
}

// Layout CRUD
export function createLayout(
  _ownerKey: string | undefined,
  payload: CreateLayoutPayload
): Promise<{ layout: PageLayout; items: PageItem[]; required_libs: string[] }> {
  return request('POST', 'layouts', payload)
}

export function updateLayout(
  layoutId: string,
  payload: UpdateLayoutPayload
): Promise<{ layout: PageLayout }> {
  return lookup('PUT', `layouts/${layoutId}`, payload)
}

export function deleteLayout(
  layoutId: string
): Promise<{ deleted: boolean; items: PageItem[]; required_libs: string[] }> {
  return lookup('DELETE', `layouts/${layoutId}`)
}

// Preview
export function getPreview(
  widgetId: string,
  signal?: AbortSignal
): Promise<{ id: string; html: string; required_libs: string[] }> {
  return lookup('GET', `widgets/${widgetId}/preview`, undefined, signal)
}

// Lookups
export function getWidgetTypes(
  pageType: string
): Promise<{ widget_types: WidgetType[] }> {
  return lookup('GET', `widget-types?page_type=${encodeURIComponent(pageType)}`)
}

export function getCollections(): Promise<{ collections: Collection[] }> {
  return lookup('GET', 'collections')
}

export function getCollectionFields(
  handle: string
): Promise<{ fields: { key: string; label: string; type: string }[] }> {
  return lookup('GET', `collections/${encodeURIComponent(handle)}/fields`)
}

export function getTags(): Promise<{ tags: Tag[] }> {
  return lookup('GET', 'tags')
}

export function getPages(): Promise<{ pages: PageRef[] }> {
  return lookup('GET', 'pages')
}

export function getEvents(): Promise<{ events: EventRef[] }> {
  return lookup('GET', 'events')
}

export function getDataSource(
  source: string
): Promise<{ options: Record<string, string> }> {
  return lookup('GET', `data-sources/${encodeURIComponent(source)}`)
}

// Image upload
export async function uploadImage(
  widgetId: string,
  key: string,
  file: File
): Promise<{ media_id: number; url: string }> {
  const url = `${lookupUrl}/widgets/${widgetId}/image`
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
  return lookup('DELETE', `widgets/${widgetId}/image/${encodeURIComponent(key)}`)
}

// Appearance background image
export async function uploadAppearanceImage(
  widgetId: string,
  file: File
): Promise<{ url: string }> {
  const url = `${lookupUrl}/widgets/${widgetId}/appearance-image`
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
  return lookup('DELETE', `widgets/${widgetId}/appearance-image`)
}

// Color swatches
export function saveColorSwatches(
  swatches: string[]
): Promise<{ swatches: string[] }> {
  return lookup('PUT', 'color-swatches', { swatches })
}

// Widget presets (designer drafts)
export function createDraftPreset(
  widgetTypeId: string,
  widgetId: string
): Promise<{ preset: WidgetDraftPreset }> {
  return lookup('POST', 'widget-presets', {
    widget_type_id: widgetTypeId,
    widget_id: widgetId,
  })
}

export function updateDraftPreset(
  presetId: string,
  payload: { label?: string; description?: string | null; handle?: string }
): Promise<{ preset: WidgetDraftPreset }> {
  return lookup('PATCH', `widget-presets/${presetId}`, payload)
}

export function deleteDraftPreset(
  presetId: string
): Promise<{ deleted: boolean }> {
  return lookup('DELETE', `widget-presets/${presetId}`)
}

export function exportDefaults(
  widgetId: string
): Promise<{ php: string }> {
  return lookup('POST', 'widget-defaults/export', { widget_id: widgetId })
}
