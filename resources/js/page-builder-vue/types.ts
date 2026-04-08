export interface Widget {
  id: string
  widget_type_id: string
  widget_type_handle: string
  widget_type_label: string
  widget_type_collections: string[]
  widget_type_config_schema: FieldDef[]
  widget_type_assets: Record<string, any>
  widget_type_default_open: boolean
  parent_widget_id: string | null
  column_index: number | null
  label: string
  config: Record<string, any>
  query_config: Record<string, any>
  style_config: Record<string, any>
  sort_order: number
  is_active: boolean
  is_required: boolean
  preview_html: string
  children: Record<number, Widget[]>
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

export interface BootstrapData {
  page_id: string
  page_type: string
  widgets: Widget[]
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
}

export interface TreeResponse {
  widgets: Widget[]
  required_libs: string[]
}

export interface CreateWidgetPayload {
  widget_type_id: string
  label?: string
  parent_widget_id?: string | null
  column_index?: number | null
  insert_position?: number | null
}

export interface UpdateWidgetPayload {
  label?: string
  config?: Record<string, any>
  style_config?: Record<string, any>
  query_config?: Record<string, any>
}

export interface ReorderItem {
  id: string
  parent_widget_id: string | null
  column_index: number | null
  sort_order: number
}
