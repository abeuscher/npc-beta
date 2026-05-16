import type { FontValue, FontFamilyOption } from '../page-builder-vue/components/primitives/FontInput.vue'
import type { SpacingValue } from '../page-builder-vue/components/primitives/SpacingInput.vue'

export type { FontValue, FontFamilyOption, SpacingValue }

export type ElementKey = 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6' | 'p' | 'ul_li' | 'ol_li'

export type BreakpointKey = 'xl' | 'lg' | 'md' | 'sm'

export interface SizeValue {
  value: number
  unit: string
}

export type ResponsiveSize = Record<BreakpointKey, SizeValue>

// Typography elements carry a per-breakpoint size; the shared FontInput
// primitive still works on a flat {value,unit} (its `size` is the xl tier —
// adapted at the panel boundary so the page-builder primitive is untouched).
export type ElementFont = Omit<FontValue, 'size'> & { size: ResponsiveSize }

export interface ElementConfig {
  font: ElementFont
  margin: SpacingValue
  padding: SpacingValue
  heading_margin_bottom?: number
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
