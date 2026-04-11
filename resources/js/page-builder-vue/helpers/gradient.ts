export type GradientType = 'linear' | 'radial'

export interface GradientLayer {
  type: GradientType
  from: string
  to: string
  angle?: number
  css_override?: string
}

export interface GradientValue {
  gradients: GradientLayer[]
}

const HEX_PATTERN = /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/
const CSS_OVERRIDE_PATTERN = /^(?:linear|radial)-gradient\(\s*[#0-9a-fA-F,\s%.deg-]+\)$/

function sanitizeHex(value: unknown): string | null {
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  if (!HEX_PATTERN.test(trimmed)) return null
  return trimmed.toLowerCase()
}

function sanitizeAngle(value: unknown): number {
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    if (typeof value === 'string' && value !== '') {
      const parsed = parseInt(value, 10)
      if (Number.isFinite(parsed) && parsed >= 0 && parsed <= 360) return parsed
    }
    return 180
  }
  const int = Math.trunc(value)
  if (int < 0 || int > 360) return 180
  return int
}

function sanitizeOverride(override: string): string {
  const trimmed = override.trim()
  if (!CSS_OVERRIDE_PATTERN.test(trimmed)) return ''
  return trimmed
}

function composeOne(gradient: GradientLayer): string {
  const override = gradient.css_override
  if (typeof override === 'string' && override !== '') {
    return sanitizeOverride(override)
  }

  const type = gradient.type
  if (type !== 'linear' && type !== 'radial') return ''

  const from = sanitizeHex(gradient.from)
  const to = sanitizeHex(gradient.to)
  if (from === null || to === null) return ''

  if (type === 'radial') {
    return `radial-gradient(${from}, ${to})`
  }

  const angle = sanitizeAngle(gradient.angle ?? 180)
  return `linear-gradient(${angle}deg, ${from}, ${to})`
}

/**
 * Compose a CSS background-image string from a structured gradient value.
 * Returns an empty string when the value is null, malformed, or contains
 * no valid gradient layers. Multi-gradient stacks are emitted with the
 * second gradient first (so it paints on top of the first).
 */
export function composeGradientCss(value: GradientValue | null | undefined): string {
  if (!value || !Array.isArray(value.gradients) || value.gradients.length === 0) {
    return ''
  }

  const layers: string[] = []
  for (const gradient of value.gradients) {
    if (gradient === null || typeof gradient !== 'object') continue
    const layer = composeOne(gradient)
    if (layer !== '') layers.push(layer)
  }

  if (layers.length === 0) return ''

  return layers.reverse().join(', ')
}
