<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import ColorPicker from './ColorPicker.vue'

export interface BorderValue {
  top: boolean
  right: boolean
  bottom: boolean
  left: boolean
  width: number
  color: string
  radius: number
  // Interior gridlines — only surfaced when `allowInterior` is set (the Table
  // widget). They share the border's width + colour; no separate swatch.
  inner_horizontal: boolean
  inner_vertical: boolean
}

const props = defineProps<{
  modelValue: Partial<BorderValue> | null | undefined
  label?: string
  // Opt-in: show interior horizontal/vertical line toggles and hide the radius
  // control (a radius makes no sense on a gridded box). Off for every widget
  // except the Table widget, so the shared control is unchanged elsewhere.
  allowInterior?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: BorderValue]
}>()

const sides = ['top', 'right', 'bottom', 'left'] as const
type Side = typeof sides[number]

function coerceInt(v: number | string | null | undefined): number {
  if (v === null || v === undefined || v === '') return 0
  const n = typeof v === 'number' ? v : parseInt(v, 10)
  return Number.isFinite(n) && n > 0 ? n : 0
}

const current = computed<BorderValue>(() => ({
  top:    !!props.modelValue?.top,
  right:  !!props.modelValue?.right,
  bottom: !!props.modelValue?.bottom,
  left:   !!props.modelValue?.left,
  width:  coerceInt(props.modelValue?.width),
  color:  props.modelValue?.color ?? '#000000',
  radius: coerceInt(props.modelValue?.radius),
  inner_horizontal: !!props.modelValue?.inner_horizontal,
  inner_vertical:   !!props.modelValue?.inner_vertical,
}))

function toggleSide(side: Side): void {
  emit('update:modelValue', { ...current.value, [side]: !current.value[side] })
}

type Interior = 'inner_horizontal' | 'inner_vertical'

function toggleInterior(key: Interior): void {
  emit('update:modelValue', { ...current.value, [key]: !current.value[key] })
}

function setWidth(raw: string): void {
  emit('update:modelValue', { ...current.value, width: coerceInt(raw) })
}

function setRadius(raw: string): void {
  emit('update:modelValue', { ...current.value, radius: coerceInt(raw) })
}

function setColor(hex: string): void {
  emit('update:modelValue', { ...current.value, color: hex })
}

// Color picker opens BELOW the row as a panel-only ColorPicker, mirroring
// BackgroundPanel's swatch-handle pattern. Rendering the popover inside the
// row column would stretch the flex row and push Width/Radius out of view.
//
// The open/close pair also has to be still: on open, the picker sometimes
// renders past the bottom of the inspector pane and the click looks like a
// no-op (the dropdown is invisible) — so we scrollIntoView('nearest') it.
// On close, the picker's height collapses and content below jumps — so we
// hold the measured panel height as an invisible spacer after the first
// open. Net effect: one ever-so-slight expansion on the very first click,
// then dead-still open/close from then on. Policy is "keep the max ever
// measured" so a future "My swatches" growth still doesn't shrink the
// reservation.
const openColor = ref(false)
const colorPanelEl = ref<HTMLDivElement | null>(null)
const reservedColorHeight = ref(0)

function toggleColor(): void {
  openColor.value = !openColor.value
}

watch(openColor, async (now) => {
  if (!now) return
  await nextTick()
  const el = colorPanelEl.value
  if (!el) return
  const h = el.offsetHeight
  if (h > reservedColorHeight.value) reservedColorHeight.value = h
  el.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
})

// Edge geometry inside the 48-unit viewBox — matches NinePointAlignment's frame.
const edges: { key: Side; x1: number; y1: number; x2: number; y2: number }[] = [
  { key: 'top',    x1: 8,  y1: 8,  x2: 40, y2: 8 },
  { key: 'right',  x1: 40, y1: 8,  x2: 40, y2: 40 },
  { key: 'bottom', x1: 8,  y1: 40, x2: 40, y2: 40 },
  { key: 'left',   x1: 8,  y1: 8,  x2: 8,  y2: 40 },
]

// Interior gridlines — a centre cross inside the 48-unit frame. Horizontal =
// lines between rows; vertical = lines between columns.
const interiorEdges: { key: Interior; x1: number; y1: number; x2: number; y2: number }[] = [
  { key: 'inner_horizontal', x1: 8,  y1: 24, x2: 40, y2: 24 },
  { key: 'inner_vertical',   x1: 24, y1: 8,  x2: 24, y2: 40 },
]
</script>

<template>
  <div class="border-input">
    <p v-if="label" class="inspector-section-title border-input__label">{{ label }}</p>
    <div class="border-input__row">
      <div class="border-input__sides">
        <label class="inspector-label border-input__field-label">Sides</label>
        <div
          class="border-input__box"
          role="group"
          aria-label="Border edges"
        >
          <svg
            class="border-input__svg"
            viewBox="0 0 48 48"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
          >
            <rect x="1" y="1" width="46" height="46" class="border-input__bg" />
            <g
              v-for="edge in edges"
              :key="edge.key"
              class="border-input__edge"
              :class="{ 'border-input__edge--on': current[edge.key] }"
              @click="toggleSide(edge.key)"
            >
              <line
                :x1="edge.x1" :y1="edge.y1" :x2="edge.x2" :y2="edge.y2"
                class="border-input__hit"
              />
              <line
                :x1="edge.x1" :y1="edge.y1" :x2="edge.x2" :y2="edge.y2"
                class="border-input__line"
              />
              <title>{{ edge.key }}</title>
            </g>
            <g
              v-for="edge in (allowInterior ? interiorEdges : [])"
              :key="edge.key"
              class="border-input__edge"
              :class="{ 'border-input__edge--on': current[edge.key] }"
              @click="toggleInterior(edge.key)"
            >
              <line
                :x1="edge.x1" :y1="edge.y1" :x2="edge.x2" :y2="edge.y2"
                class="border-input__hit"
              />
              <line
                :x1="edge.x1" :y1="edge.y1" :x2="edge.x2" :y2="edge.y2"
                class="border-input__line border-input__line--interior"
              />
              <title>{{ edge.key === 'inner_horizontal' ? 'interior rows' : 'interior columns' }}</title>
            </g>
          </svg>
        </div>
      </div>

      <div class="border-input__color">
        <label class="inspector-label border-input__field-label">Color</label>
        <button
          type="button"
          class="border-input__swatch"
          :class="{ 'border-input__swatch--active': openColor }"
          :style="{ backgroundColor: current.color }"
          :aria-label="`Border color: ${current.color}`"
          :aria-expanded="openColor"
          @click.stop="toggleColor"
        />
      </div>

      <div class="border-input__field">
        <label class="inspector-label border-input__field-label">Width</label>
        <input
          type="number"
          min="0"
          :value="current.width"
          class="inspector-control border-input__input"
          @input="setWidth(($event.target as HTMLInputElement).value)"
        >
      </div>

      <div v-if="!allowInterior" class="border-input__field">
        <label class="inspector-label border-input__field-label">Radius</label>
        <input
          type="number"
          min="0"
          :value="current.radius"
          class="inspector-control border-input__input"
          @input="setRadius(($event.target as HTMLInputElement).value)"
        >
      </div>
    </div>

    <div v-if="openColor" ref="colorPanelEl" class="border-input__color-panel">
      <ColorPicker
        :model-value="current.color"
        panel-only
        @update:model-value="setColor"
      />
    </div>
    <div
      v-else-if="reservedColorHeight > 0"
      class="border-input__color-spacer"
      :style="{ height: `${reservedColorHeight}px` }"
      aria-hidden="true"
    />
  </div>
</template>

<style scoped>
.border-input {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.border-input__label {
  margin-bottom: 0.5rem;
}

.border-input__row {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
}

.border-input__sides {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  flex-shrink: 0;
}

.border-input__box {
  /* Match the color swatch's outer footprint minus 2px so the "on" stroke
   * (1px thicker than off) doesn't make the box visually overflow. */
  width: calc(2rem - 2px);
  height: calc(2rem - 2px);
  flex-shrink: 0;
}

.border-input__svg {
  width: 100%;
  height: 100%;
  display: block;
}

.border-input__bg {
  fill: #f9fafb;
  stroke: none;
}

.border-input__edge {
  cursor: pointer;
}

.border-input__hit {
  /* Sized in viewBox units (0–48). 14 → ~9px rendered hit zone per edge at
   * the 30px box. butt cap (vs round) keeps the hit zone perpendicular to
   * the edge so neighboring edges don't overlap at the corners — a click
   * near a corner has no ambiguity about which side it toggles. */
  stroke: transparent;
  stroke-width: 14;
  stroke-linecap: butt;
}

.border-input__line {
  stroke: #ccc;
  stroke-width: 2;
  stroke-linecap: round;
  vector-effect: non-scaling-stroke;
  transition: stroke 0.1s ease, stroke-width 0.1s ease;
}

.border-input__line--interior {
  stroke-dasharray: 3 2;
}

.border-input__edge:hover .border-input__line {
  stroke: #666;
}

.border-input__edge--on .border-input__line {
  stroke: #000;
  stroke-width: 3;
}

.border-input__color {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  flex-shrink: 0;
}

.border-input__swatch {
  width: 2rem;
  height: 2rem;
  border: 1px solid var(--np-control-border);
  border-radius: var(--np-control-radius);
  cursor: pointer;
  padding: 0;
  box-sizing: border-box;
  transition: var(--np-control-transition);
}

.border-input__swatch:hover {
  border-color: var(--np-control-border-hover);
}

.border-input__swatch--active {
  border-color: var(--np-control-border-active);
  box-shadow: 0 0 0 1px var(--np-control-border-active);
}

.border-input__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
  flex: 1;
}

.border-input__field-label {
  text-align: center;
  color: #9ca3af;
}

.border-input__input {
  width: 100%;
  min-width: 0;
  height: 2rem;
  padding: 0.25rem;
  text-align: center;
  box-sizing: border-box;
  -moz-appearance: textfield;
  appearance: textfield;
}

.border-input__input::-webkit-outer-spin-button,
.border-input__input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.border-input__color-panel {
  margin-top: 0.5rem;
}

/* Wrapper supplies the gap above the panel — reset ColorPicker's own
 * popover margin-top so the spacing is owned by one element, not two. */
.border-input__color-panel :deep(.color-picker__popover) {
  margin-top: 0;
}

.border-input__color-spacer {
  margin-top: 0.5rem;
}

html.dark .border-input__bg                              { fill: rgb(31 41 55); stroke: none; }
html.dark .border-input__line                            { stroke: rgb(107 114 128); }
html.dark .border-input__edge:hover .border-input__line  { stroke: rgb(209 213 219); }
html.dark .border-input__edge--on .border-input__line    { stroke: #fff; }
</style>
