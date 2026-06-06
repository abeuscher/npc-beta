export type GradientType = 'linear' | 'radial'

export interface GradientLayer {
  type: GradientType
  from: string
  to: string
  angle?: number
  from_alpha?: number
  to_alpha?: number
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

function clampAlpha(value: unknown): number {
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    if (typeof value === 'string' && value !== '') {
      const parsed = parseInt(value, 10)
      if (Number.isFinite(parsed)) return Math.max(0, Math.min(100, parsed))
    }
    return 100
  }
  return Math.max(0, Math.min(100, Math.trunc(value)))
}

function hexToRgba(hex: string, alpha: number): string {
  const h = hex.replace('#', '')
  let r: number, g: number, b: number
  if (h.length === 3) {
    r = parseInt(h[0] + h[0], 16)
    g = parseInt(h[1] + h[1], 16)
    b = parseInt(h[2] + h[2], 16)
  } else {
    r = parseInt(h.substring(0, 2), 16)
    g = parseInt(h.substring(2, 4), 16)
    b = parseInt(h.substring(4, 6), 16)
  }
  const a = Math.round(alpha) / 100
  return `rgba(${r}, ${g}, ${b}, ${a})`
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

  const fromAlpha = clampAlpha(gradient.from_alpha ?? 100)
  const toAlpha = clampAlpha(gradient.to_alpha ?? 100)

  const fromColor = fromAlpha < 100 ? hexToRgba(from, fromAlpha) : from
  const toColor = toAlpha < 100 ? hexToRgba(to, toAlpha) : to

  if (type === 'radial') {
    return `radial-gradient(${fromColor}, ${toColor})`
  }

  const angle = sanitizeAngle(gradient.angle ?? 180)
  return `linear-gradient(${angle}deg, ${fromColor}, ${toColor})`
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
