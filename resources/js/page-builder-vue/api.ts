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

export interface ApiClient {
  // Widget CRUD — owner-scoped
  getWidgets(): Promise<TreeResponse>
  createWidget(payload: CreateWidgetPayload): Promise<{ widget: Widget; items: PageItem[]; required_libs: string[] }>
  updateWidget(widgetId: string, payload: UpdateWidgetPayload): Promise<{ widget: Widget }>
  deleteWidget(widgetId: string): Promise<{ deleted: boolean; items: PageItem[]; required_libs: string[] }>
  copyWidget(widgetId: string): Promise<{ widget: Widget; items: PageItem[]; required_libs: string[] }>
  reorderWidgets(items: ReorderItem[]): Promise<{ items: PageItem[]; required_libs: string[] }>

  // Layout CRUD
  createLayout(payload: CreateLayoutPayload): Promise<{ layout: PageLayout; items: PageItem[]; required_libs: string[] }>
  updateLayout(layoutId: string, payload: UpdateLayoutPayload): Promise<{ layout: PageLayout }>
  deleteLayout(layoutId: string): Promise<{ deleted: boolean; items: PageItem[]; required_libs: string[] }>

  // Preview
  getPreview(widgetId: string, signal?: AbortSignal): Promise<{ id: string; html: string; required_libs: string[] }>

  // Lookups
  getWidgetTypes(pageType: string): Promise<{ widget_types: WidgetType[] }>
  getCollections(): Promise<{ collections: Collection[] }>
  getCollectionFields(handle: string): Promise<{ fields: { key: string; label: string; type: string }[] }>
  getTags(): Promise<{ tags: Tag[] }>
  getPages(): Promise<{ pages: PageRef[] }>
  getEvents(): Promise<{ events: EventRef[] }>
  getDataSource(source: string): Promise<{ options: Record<string, string> }>

  // Image upload
  uploadImage(widgetId: string, key: string, file: File): Promise<{ media_id: number; url: string }>
  removeImage(widgetId: string, key: string): Promise<{ removed: boolean }>

  // Appearance background image
  uploadAppearanceImage(widgetId: string, file: File): Promise<{ url: string }>
  removeAppearanceImage(widgetId: string): Promise<{ removed: boolean }>

  // Color swatches
  saveColorSwatches(swatches: string[]): Promise<{ swatches: string[] }>

  // Widget presets
  createDraftPreset(widgetTypeId: string, widgetId: string): Promise<{ preset: WidgetDraftPreset }>
  updateDraftPreset(presetId: string, payload: { label?: string; description?: string | null; handle?: string }): Promise<{ preset: WidgetDraftPreset }>
  deleteDraftPreset(presetId: string): Promise<{ deleted: boolean }>

  // Widget defaults export
  exportDefaults(widgetId: string): Promise<{ php: string }>
}

/**
 * Build an owner-scoped API client. Each Vue app instance must create its own
 * client so the owner-scoped `baseUrl` doesn't collide between instances when
 * multiple page-builders render on the same page (e.g. template header + footer).
 *
 * - `baseUrl`: owner-scoped base (e.g. `/admin/api/page-builder/pages/{uuid}`).
 *   Used for widget/layout CRUD under a specific owner.
 * - `lookupUrl`: owner-agnostic lookup base (e.g. `/admin/api/page-builder`).
 *   Used for widget-types, collections, tags, pages, events, data-sources,
 *   widget-presets, widget-defaults, color-swatches, and widget- and
 *   layout-keyed routes (update/delete/copy/preview/image). Defaults to the
 *   parent of `baseUrl` when omitted.
 */
export function createApiClient(
  csrfToken: string,
  rawBaseUrl: string,
  rawLookupUrl?: string,
): ApiClient {
  const baseUrl = rawBaseUrl.replace(/\/$/, '')
  const lookupUrl = (rawLookupUrl ?? baseUrl.replace(/\/(pages|templates)\/[^/]+$/, '')).replace(/\/$/, '')

  async function requestAt<T>(
    base: string,
    method: string,
    path: string,
    body?: any,
    signal?: AbortSignal,
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

  function request<T>(method: string, path: string, body?: any, signal?: AbortSignal): Promise<T> {
    return requestAt<T>(baseUrl, method, path, body, signal)
  }

  function lookup<T>(method: string, path: string, body?: any, signal?: AbortSignal): Promise<T> {
    return requestAt<T>(lookupUrl, method, path, body, signal)
  }

  async function postFormToLookup<T>(path: string, formData: FormData): Promise<T> {
    const url = `${lookupUrl}/${path.replace(/^\//, '')}`

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
      throw new Error(`POST ${path} failed (${res.status}): ${text}`)
    }

    return res.json()
  }

  return {
    // Widget CRUD — owner-scoped (baseUrl already encodes the owner).
    getWidgets() {
      return request('GET', 'widgets')
    },
    createWidget(payload) {
      return request('POST', 'widgets', payload)
    },
    updateWidget(widgetId, payload) {
      return lookup('PUT', `widgets/${widgetId}`, payload)
    },
    deleteWidget(widgetId) {
      return lookup('DELETE', `widgets/${widgetId}`)
    },
    copyWidget(widgetId) {
      return lookup('POST', `widgets/${widgetId}/copy`)
    },
    reorderWidgets(items) {
      return request('PUT', 'widgets/reorder', { items })
    },

    // Layout CRUD
    createLayout(payload) {
      return request('POST', 'layouts', payload)
    },
    updateLayout(layoutId, payload) {
      return lookup('PUT', `layouts/${layoutId}`, payload)
    },
    deleteLayout(layoutId) {
      return lookup('DELETE', `layouts/${layoutId}`)
    },

    // Preview
    getPreview(widgetId, signal) {
      return lookup('GET', `widgets/${widgetId}/preview`, undefined, signal)
    },

    // Lookups
    getWidgetTypes(pageType) {
      return lookup('GET', `widget-types?page_type=${encodeURIComponent(pageType)}`)
    },
    getCollections() {
      return lookup('GET', 'collections')
    },
    getCollectionFields(handle) {
      return lookup('GET', `collections/${encodeURIComponent(handle)}/fields`)
    },
    getTags() {
      return lookup('GET', 'tags')
    },
    getPages() {
      return lookup('GET', 'pages')
    },
    getEvents() {
      return lookup('GET', 'events')
    },
    getDataSource(source) {
      return lookup('GET', `data-sources/${encodeURIComponent(source)}`)
    },

    // Image upload
    uploadImage(widgetId, key, file) {
      const formData = new FormData()
      formData.append('key', key)
      formData.append('file', file)
      return postFormToLookup(`widgets/${widgetId}/image`, formData)
    },
    removeImage(widgetId, key) {
      return lookup('DELETE', `widgets/${widgetId}/image/${encodeURIComponent(key)}`)
    },

    // Appearance background image
    uploadAppearanceImage(widgetId, file) {
      const formData = new FormData()
      formData.append('file', file)
      return postFormToLookup(`widgets/${widgetId}/appearance-image`, formData)
    },
    removeAppearanceImage(widgetId) {
      return lookup('DELETE', `widgets/${widgetId}/appearance-image`)
    },

    // Color swatches
    saveColorSwatches(swatches) {
      return lookup('PUT', 'color-swatches', { swatches })
    },

    // Widget presets (designer drafts)
    createDraftPreset(widgetTypeId, widgetId) {
      return lookup('POST', 'widget-presets', {
        widget_type_id: widgetTypeId,
        widget_id: widgetId,
      })
    },
    updateDraftPreset(presetId, payload) {
      return lookup('PATCH', `widget-presets/${presetId}`, payload)
    },
    deleteDraftPreset(presetId) {
      return lookup('DELETE', `widget-presets/${presetId}`)
    },

    // Widget defaults export
    exportDefaults(widgetId) {
      return lookup('POST', 'widget-defaults/export', { widget_id: widgetId })
    },
  }
}
