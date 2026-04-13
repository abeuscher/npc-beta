export interface WidgetAppearanceConfig {
  background?: {
    color?: string
    gradient?: any
    image_id?: string | number | null
    alignment?: string
    fit?: string
    overlay?: {
      enabled?: boolean
      color?: string
      opacity?: number
    }
  }
  text?: {
    color?: string
  }
  layout?: {
    full_width?: boolean
    padding?: { top?: string | number; right?: string | number; bottom?: string | number; left?: string | number }
    margin?: { top?: string | number; right?: string | number; bottom?: string | number; left?: string | number }
  }
}

export interface Widget {
  id: string
  widget_type_id: string
  widget_type_handle: string
  widget_type_label: string
  widget_type_collections: string[]
  widget_type_config_schema: FieldDef[]
  widget_type_assets: Record<string, any>
  widget_type_default_open: boolean
  widget_type_required_config: { keys: string[]; message: string } | null
  layout_id: string | null
  column_index: number | null
  label: string
  config: Record<string, any>
  resolved_defaults: Record<string, any>
  query_config: Record<string, any>
  appearance_config: WidgetAppearanceConfig
  sort_order: number
  is_active: boolean
  is_required: boolean
  image_urls: Record<string, string | null>
  appearance_image_url: string | null
  preview_html: string
}

export interface PageLayout {
  id: string
  page_id: string
  label: string
  display: 'flex' | 'grid'
  columns: number
  layout_config: Record<string, any>
  sort_order: number
  slots: Record<number, Widget[]>
}

export type WidgetItem = Widget & { type: 'widget' }
export type LayoutItem = PageLayout & { type: 'layout' }
export type PageItem = WidgetItem | LayoutItem

export interface WidgetPreset {
  handle: string
  label: string
  description?: string | null
  config: Record<string, any>
  appearance_config: WidgetAppearanceConfig
}

export interface WidgetDraftPreset extends WidgetPreset {
  id: string
  is_draft: true
}

export interface WidgetType {
  id: string
  handle: string
  label: string
  description: string | null
  category: string[]
  config_schema: FieldDef[]
  collections: string[]
  assets: Record<string, any>
  full_width: boolean
  default_open: boolean
  required_config: { keys: string[]; message: string } | null
  presets: WidgetPreset[]
  draft_presets?: WidgetDraftPreset[]
  thumbnail: string | null
  thumbnail_hover: string | null
}

export interface FieldDef {
  key: string
  label: string
  type: string
  tab?: string
  group?: string
  options?: Record<string, string>
  options_from?: string
  depends_on?: string
  default?: any
  [key: string]: any
}

export interface Collection {
  handle: string
  name: string
  source_type: string
}

export interface Tag {
  id: string
  name: string
  slug: string
}

export interface PageRef {
  slug: string
  title: string
}

export interface EventRef {
  slug: string
  title: string
}

export interface ThemePaletteEntry {
  key: string
  label: string
  value: string | null
}

export interface BootstrapData {
  page_id: string
  page_type: string
  page_title: string
  page_author: string
  page_status: string
  page_url: string
  page_tags: string[]
  details_url: string | null
  items: PageItem[]
  required_libs: string[]
  widget_types: WidgetType[]
  required_handles: string[]
  collections: Collection[]
  tags: Tag[]
  pages: PageRef[]
  events: EventRef[]
  csrf_token: string
  api_base_url: string
  inline_image_upload_url: string
  color_swatches: string[]
  theme_palette: ThemePaletteEntry[]
}

export interface TreeResponse {
  items: PageItem[]
  required_libs: string[]
}

export interface CreateWidgetPayload {
  widget_type_id: string
  label?: string
  layout_id?: string | null
  column_index?: number | null
  insert_position?: number | null
}

export interface UpdateWidgetPayload {
  label?: string
  config?: Record<string, any>
  appearance_config?: WidgetAppearanceConfig
  query_config?: Record<string, any>
}

export interface CreateLayoutPayload {
  label?: string
  display?: 'flex' | 'grid'
  columns?: number
}

export interface UpdateLayoutPayload {
  label?: string
  display?: 'flex' | 'grid'
  columns?: number
  layout_config?: Record<string, any>
}

export type ReorderItem =
  | {
      id: string
      type: 'widget'
      layout_id: string | null
      column_index: number | null
      sort_order: number
    }
  | {
      id: string
      type: 'layout'
      sort_order: number
    }
