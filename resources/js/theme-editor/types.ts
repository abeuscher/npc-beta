import type { FontValue, FontFamilyOption } from '../page-builder-vue/components/primitives/FontInput.vue'
import type { SpacingValue } from '../page-builder-vue/components/primitives/SpacingInput.vue'

export type { FontValue, FontFamilyOption, SpacingValue }

export type ElementKey = 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6' | 'p' | 'ul_li' | 'ol_li'

export interface ElementConfig {
  font: FontValue
  margin: SpacingValue
  padding: SpacingValue
  list_style_type?: string
  marker_color?: string | null
}

export interface TypographyState {
  buckets: {
    heading_family: string | null
    body_family: string | null
    nav_family: string | null
  }
  elements: Record<ElementKey, ElementConfig>
  sample_text: string
}

export interface TypographyBootstrap {
  typography: TypographyState
  families: FontFamilyOption[]
  saveUrl: string
  exportUrl: string
  csrfToken: string
}
