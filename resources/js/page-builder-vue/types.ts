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
    background_full_width?: boolean
    content_full_width?: boolean
    padding?: { top?: string | number; right?: string | number; bottom?: string | number; left?: string | number }
    margin?: { top?: string | number; right?: string | number; bottom?: string | number; left?: string | number }
  }
}

export interface QuerySettingsDescriptor {
  has_panel: boolean
  order_by_options: Record<string, string>
  supports_tags: boolean
}

export interface Widget {
  id: string
  widget_type_id: string
  widget_type_handle: string
  widget_type_label: string
  widget_type_config_schema: FieldDef[]
  widget_type_assets: Record<string, any>
  widget_type_default_open: boolean
  widget_type_required_config: { keys: string[]; message: string } | null
  widget_type_inline_editable: boolean
  layout_id: string | null
  column_index: number | null
  label: string
  config: Record<string, any>
  resolved_defaults: Record<string, any>
  query_config: Record<string, any>
  query_settings: QuerySettingsDescriptor | null
  appearance_config: WidgetAppearanceConfig
  sort_order: number
  is_active: boolean
  is_required: boolean
  image_urls: Record<string, string | null>
  appearance_image_url: string | null
  preview_html: string
}

export interface LayoutAppearanceConfig {
  background?: {
    color?: string
    gradient?: any
    alignment?: string
    fit?: string
  }
  layout?: {
    padding?: { top?: string | number; right?: string | number; bottom?: string | number; left?: string | number }
    margin?: { top?: string | number; right?: string | number; bottom?: string | number; left?: string | number }
  }
}

export interface PageLayout {
  id: string
  page_id: string
  label: string
  display: 'flex' | 'grid'
  columns: number
  layout_config: Record<string, any>
  appearance_config: LayoutAppearanceConfig
  inline_style: string
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
  thumbnail?: string | null
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
  background_full_width: boolean
  content_full_width: boolean
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
  // Session 305 §6.3: resolved URL for this page, populated by the
  // page-builder bootstrap so the inline toolbar's link popover can
  // populate the URL field on selection. Optional because other mount
  // points (dashboard, record-detail) may not provide it.
  url?: string
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

export type EditorMode = 'page' | 'dashboard' | 'record_detail'

// Read-only header/footer context band around the editable page flow.
// styles ride the blade (emitted server-side); Vue consumes html + edit_url.
export interface ChromeBand {
  html: string
  styles?: string
  edit_url: string | null
}

export interface BootstrapData {
  mode?: EditorMode
  owner_id?: string
  owner_type?: string
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
  api_lookup_url?: string
  inline_image_upload_url: string
  heroicons_url?: string
  color_swatches: string[]
  theme_palette: ThemePaletteEntry[]
  // Session 305 §6.3: resolved theme font-family stacks. The inline
  // toolbar's text-style menu renders Paragraph + H1–H6 rows in these
  // families so the dropdown is a true WYSIWYG preview. Optional because
  // only the page-builder bootstrap currently provides them.
  theme_heading_family?: string
  theme_body_family?: string
  theme_editor_url: string
  chrome?: { header: ChromeBand | null; footer: ChromeBand | null }
  allowed_appearance_fields?: string[]
  allowed_widget_handles?: string[]
  role_label?: string
  view_label?: string
  record_type_label?: string
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
  appearance_config?: LayoutAppearanceConfig
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

export interface DedupMatch {
  id: number
  file_name: string
  collection_name: string
  size: number
  mime_type: string | null
  created_at: string | null
  match_type: 'identical' | 'same_name'
  duplicate_count: number
  referenced: boolean
  url: string | null
}

export type DedupDecision =
  | { type: 'keep-new' }
  | { type: 'use-existing'; mediaId: number }
  | { type: 'cancel' }
